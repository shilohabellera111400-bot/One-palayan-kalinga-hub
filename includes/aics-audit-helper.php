<?php
/**
 * AICS Frequency & Duplicate Assistance Audit Protection Helper
 * 
 * Enforces COA & DSWD 90-day cooling-off frequency guidelines:
 * - Detects duplicate or recent assistance grants to an individual/household within 90 days.
 * - Flags frequency violations before approval or payout to prevent audit disallowances.
 * - Requires explicit Social Worker Justification (audit override) when approving within 90 days.
 * - Provides batch checking for high-performance Kanban and Table views.
 */

if (!function_exists('checkAicsFrequency')) {
    /**
     * Check frequency and assistance history for a specific applicant.
     * 
     * @param PDO $db
     * @param int $user_id
     * @param int $current_aics_id Exclude the current request if reviewing
     * @param string|null $reference_date Defaults to current date/time
     * @return array
     */
    function checkAicsFrequency(PDO $db, int $user_id, int $current_aics_id = 0, ?string $reference_date = null): array
    {
        if ($user_id <= 0) {
            return [
                'is_flagged' => false,
                'severity' => 'clean',
                'recent_assistance' => null,
                'days_elapsed' => null,
                'days_remaining' => 0,
                'eligible_date' => null,
                'total_90d_amount' => 0.0,
                'total_all_time_grants' => 0,
                'active_pending_count' => 0,
                'history' => [],
                'audit_advice' => 'Clean audit record. No prior assistance found.'
            ];
        }

        $ref_time = $reference_date ? strtotime($reference_date) : time();
        $ref_mysql_date = date('Y-m-d H:i:s', $ref_time);

        // Fetch all prior requests for this user (both completed and active)
        $history_sql = "SELECT a.*, 
                               COALESCE(a.release_date, a.approval_date, a.submission_date) AS effective_date,
                               u.first_name, u.last_name
                        FROM aics_requests a
                        JOIN users u ON a.user_id = u.user_id
                        WHERE a.user_id = :user_id";
        
        if ($current_aics_id > 0) {
            $history_sql .= " AND a.aics_id != :current_id";
        }
        $history_sql .= " ORDER BY effective_date DESC";

        $stmt = $db->prepare($history_sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        if ($current_aics_id > 0) {
            $stmt->bindValue(':current_id', $current_aics_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $all_past = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $history = [];
        $recent_assistance = null;
        $total_90d_amount = 0.0;
        $total_all_time_grants = 0;
        $active_pending_count = 0;

        foreach ($all_past as $row) {
            $eff_time = strtotime($row['effective_date']);
            $days_ago = max(0, (int)floor(($ref_time - $eff_time) / 86400));
            $is_within_90 = ($days_ago <= 90);
            $is_granted = in_array($row['status'], ['approved', 'released']);
            $is_pending = in_array($row['status'], ['submitted', 'reviewing']);

            if ($is_pending) {
                $active_pending_count++;
            }

            if ($is_granted) {
                $total_all_time_grants++;
                if ($is_within_90) {
                    $total_90d_amount += floatval($row['amount_approved'] ?: $row['amount_requested']);
                    if ($recent_assistance === null) {
                        $recent_assistance = $row;
                        $recent_assistance['days_ago'] = $days_ago;
                    }
                }
            }

            $row['days_ago'] = $days_ago;
            $row['is_within_90'] = $is_within_90;
            $history[] = $row;
        }

        // Determine audit flag and severity
        $is_flagged = ($recent_assistance !== null);
        $days_elapsed = $recent_assistance ? $recent_assistance['days_ago'] : null;
        $days_remaining = $days_elapsed !== null ? max(0, 90 - $days_elapsed) : 0;
        $eligible_date = null;

        if ($recent_assistance) {
            $eff_time = strtotime($recent_assistance['effective_date']);
            $eligible_time = strtotime('+90 days', $eff_time);
            $eligible_date = date('F d, Y', $eligible_time);
        }

        $severity = 'clean';
        if ($is_flagged) {
            if ($days_elapsed <= 45) {
                $severity = 'high'; // Very recent grant (< 45 days)
            } else {
                $severity = 'medium'; // 46-90 days ago
            }
        } elseif ($active_pending_count > 0) {
            $severity = 'notice'; // Has other concurrent applications pending review
        }

        // Formulate audit recommendation message
        $audit_advice = '';
        if ($is_flagged) {
            $type_label = ucwords(str_replace('_', ' ', $recent_assistance['request_type']));
            $amount_label = '₱' . number_format($recent_assistance['amount_approved'] ?: $recent_assistance['amount_requested'], 2);
            $status_label = strtoupper($recent_assistance['status']);
            $tracking_no = 'AICS-' . str_pad($recent_assistance['aics_id'], 6, '0', STR_PAD_LEFT);
            $audit_advice = "Audit Notice: Applicant received {$type_label} assistance ({$amount_label}) {$days_elapsed} days ago (Status: {$status_label}, Tracking: {$tracking_no}). "
                . "The standard 90-day cooling-off period requires {$days_remaining} more days until {$eligible_date}. "
                . "Approval within this window requires licensed Social Worker Special Justification (e.g., life-saving medical emergency or calamity exception).";
        } elseif ($active_pending_count > 0) {
            $audit_advice = "Notice: Applicant currently has {$active_pending_count} other active AICS application(s) under review.";
        } else {
            $audit_advice = "Clean Audit Record: No previous assistance released within the past 90 days. Compliant with DSWD & COA guidelines.";
        }

        return [
            'is_flagged' => $is_flagged,
            'severity' => $severity,
            'recent_assistance' => $recent_assistance,
            'days_elapsed' => $days_elapsed,
            'days_remaining' => $days_remaining,
            'eligible_date' => $eligible_date,
            'total_90d_amount' => $total_90d_amount,
            'total_all_time_grants' => $total_all_time_grants,
            'active_pending_count' => $active_pending_count,
            'history' => $history,
            'audit_advice' => $audit_advice
        ];
    }
}

if (!function_exists('batchCheckAicsFrequency')) {
    /**
     * High-performance batch check for multiple applicants at once.
     * Prevents N+1 database queries on list and Kanban views.
     * 
     * @param PDO $db
     * @param array $user_ids
     * @return array Map of [user_id => audit_summary]
     */
    function batchCheckAicsFrequency(PDO $db, array $user_ids): array
    {
        $user_ids = array_values(array_filter(array_unique(array_map('intval', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        $in_clause = implode(',', $user_ids);
        
        // Single optimized query to retrieve granted assistance within the last 90 days
        $sql = "SELECT a.user_id, a.aics_id, a.request_type, a.status, a.amount_approved, a.amount_requested,
                       COALESCE(a.release_date, a.approval_date, a.submission_date) AS effective_date,
                       DATEDIFF(NOW(), COALESCE(a.release_date, a.approval_date, a.submission_date)) AS days_ago
                FROM aics_requests a
                WHERE a.user_id IN ($in_clause)
                  AND a.status IN ('approved', 'released')
                  AND DATEDIFF(NOW(), COALESCE(a.release_date, a.approval_date, a.submission_date)) <= 90
                ORDER BY effective_date DESC";

        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($user_ids as $uid) {
            $results[$uid] = [
                'is_flagged' => false,
                'days_ago' => null,
                'days_remaining' => 0,
                'request_type' => null,
                'amount' => 0,
                'tracking' => null,
                'badge_html' => ''
            ];
        }

        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            if (!$results[$uid]['is_flagged']) {
                $days_ago = max(0, (int)$r['days_ago']);
                $days_remaining = max(0, 90 - $days_ago);
                $type_label = ucwords(str_replace('_', ' ', $r['request_type']));
                $tracking = 'AICS-' . str_pad($r['aics_id'], 6, '0', STR_PAD_LEFT);
                $amt = floatval($r['amount_approved'] ?: $r['amount_requested']);

                $is_high = ($days_ago <= 45);
                $badge_bg = $is_high ? '#FEF2F2' : '#FFFBEB';
                $badge_text = $is_high ? '#991B1B' : '#92400E';
                $badge_border = $is_high ? '#FCA5A5' : '#FCD34D';
                $badge_icon = $is_high ? 'fa-shield-exclamation' : 'fa-exclamation-triangle';

                $badge_html = '<span class="audit-flag-pill" title="Audit Notice: Received ' . htmlspecialchars($type_label) . ' (₱' . number_format($amt) . ') ' . $days_ago . 'd ago (' . $days_remaining . 'd cooling-off remaining)" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 4px; font-size: 0.68rem; font-weight: 700; background: ' . $badge_bg . '; color: ' . $badge_text . '; border: 1px solid ' . $badge_border . '; white-space: nowrap; line-height: 1.2;">'
                    . '<i class="fas ' . $badge_icon . '"></i> 90d Audit Flag (' . $days_ago . 'd ago)'
                    . '</span>';

                $results[$uid] = [
                    'is_flagged' => true,
                    'days_ago' => $days_ago,
                    'days_remaining' => $days_remaining,
                    'request_type' => $r['request_type'],
                    'amount' => $amt,
                    'tracking' => $tracking,
                    'badge_html' => $badge_html
                ];
            }
        }

        return $results;
    }
}

if (!function_exists('renderAicsAuditBanner')) {
    /**
     * Render an informative, government-standard audit card for request inspection.
     * 
     * @param array $audit
     * @param array $current_request
     * @return string HTML
     */
    function renderAicsAuditBanner(array $audit, array $current_request = []): string
    {
        if (!$audit['is_flagged'] && empty($audit['active_pending_count'])) {
            return '
            <div class="audit-card audit-clean" style="background: rgba(34, 197, 94, 0.06); border: 1px solid rgba(34, 197, 94, 0.25); border-radius: 12px; padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: flex-start; gap: 14px;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(34, 197, 94, 0.15); color: #16A34A; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; margin-top: 2px;">
                    <i class="fas fa-shield-check"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                        <h4 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: #15803D;">COA & DSWD Frequency Check: Fully Compliant</h4>
                        <span style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: rgba(34, 197, 94, 0.15); color: #15803D;">90-Day Policy OK</span>
                    </div>
                    <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-secondary); line-height: 1.45;">
                        This applicant has no record of assistance released in the past 90 days. Eligible for standard assessment and processing.
                    </p>
                </div>
            </div>';
        }

        if ($audit['is_flagged']) {
            $recent = $audit['recent_assistance'];
            $days_ago = $audit['days_elapsed'];
            $days_remaining = $audit['days_remaining'];
            $type_label = ucwords(str_replace('_', ' ', $recent['request_type']));
            $amt_label = '₱' . number_format($recent['amount_approved'] ?: $recent['amount_requested'], 2);
            $tracking = 'AICS-' . str_pad($recent['aics_id'], 6, '0', STR_PAD_LEFT);
            $eff_date = date('M d, Y', strtotime($recent['effective_date']));
            $eligible_date = $audit['eligible_date'];

            // Severity styling
            $is_high = ($audit['severity'] === 'high');
            $card_border = $is_high ? '#EF4444' : '#F59E0B';
            $card_bg = $is_high ? 'rgba(239, 68, 68, 0.07)' : 'rgba(245, 158, 11, 0.07)';
            $icon_bg = $is_high ? 'rgba(239, 68, 68, 0.18)' : 'rgba(245, 158, 11, 0.18)';
            $icon_color = $is_high ? '#DC2626' : '#D97706';
            $title_color = $is_high ? '#B91C1C' : '#B45309';

            // Calculate percentage of 90 days elapsed for progress bar
            $pct = min(100, max(0, round(($days_ago / 90) * 100)));

            ob_start();
            ?>
            <div class="audit-card audit-warning" style="background: <?php echo $card_bg; ?>; border: 1.5px solid <?php echo $card_border; ?>; border-radius: 14px; padding: 18px 20px; margin-bottom: 24px; position: relative; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: <?php echo $icon_bg; ?>; color: <?php echo $icon_color; ?>; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 6px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <h3 style="margin: 0; font-size: 1rem; font-weight: 800; color: <?php echo $title_color; ?>; letter-spacing: -0.2px;">
                                    ⚠️ COA & DSWD Audit Alert: 90-Day Assistance Frequency Flag
                                </h3>
                                <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; padding: 3px 8px; border-radius: 6px; background: <?php echo $icon_color; ?>; color: #FFF;">
                                    <?php echo $days_ago; ?> Days Ago
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; font-weight: 700; color: <?php echo $title_color; ?>;">
                                Cooling-Off Ends: <strong><?php echo $eligible_date; ?></strong> (<?php echo $days_remaining; ?> days left)
                            </span>
                        </div>

                        <p style="margin: 0 0 10px 0; font-size: 0.84rem; color: var(--text-primary); line-height: 1.55;">
                            This applicant previously received <strong><?php echo htmlspecialchars($type_label); ?> Assistance</strong> amounting to <strong><?php echo $amt_label; ?></strong> on <strong><?php echo $eff_date; ?></strong> under tracking <strong><a href="view-aics.php?id=<?php echo $recent['aics_id']; ?>" target="_blank" style="color: <?php echo $title_color; ?>; text-decoration: underline;"><?php echo $tracking; ?></a></strong>.
                        </p>

                        <!-- Progress Bar of the 90-Day Cooling-off window -->
                        <div style="margin: 10px 0; background: rgba(0,0,0,0.08); border-radius: 10px; height: 8px; overflow: hidden; position: relative;">
                            <div style="width: <?php echo $pct; ?>%; background: <?php echo $icon_color; ?>; height: 100%; border-radius: 10px; transition: width 0.6s ease;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.72rem; color: var(--text-muted); margin-bottom: 12px;">
                            <span>Grant Date: <?php echo $eff_date; ?></span>
                            <span>Day <?php echo $days_ago; ?> of 90 (<?php echo $days_remaining; ?> days remaining)</span>
                            <span>Eligible: <?php echo $eligible_date; ?></span>
                        </div>

                        <!-- Policy Mandate Box -->
                        <div style="background: rgba(255,255,255,0.7); dark:background: rgba(0,0,0,0.25); border-left: 3px solid <?php echo $icon_color; ?>; padding: 10px 14px; border-radius: 6px; font-size: 0.78rem; color: var(--text-secondary); line-height: 1.5;">
                            <strong>COA & DSWD MC 15 S. 2022 Compliance Requirement:</strong><br>
                            Approval within the 90-day interval is <em>restricted</em> and strictly permissible only for recurring life-threatening medical cases (e.g., dialysis/chemo) or unforeseen disaster emergencies. If approving, staff <strong>must provide Social Worker Justification Notes</strong> in the approval modal to safeguard from COA audit disallowances.
                        </div>

                        <!-- Toggle past assistance history -->
                        <?php if (count($audit['history']) > 0): ?>
                        <div style="margin-top: 12px; display: flex; align-items: center; justify-content: space-between;">
                            <button type="button" onclick="toggleAuditHistoryTable()" style="background: none; border: none; padding: 0; font-size: 0.78rem; font-weight: 700; color: <?php echo $title_color; ?>; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas fa-history"></i> <span id="auditHistoryToggleLabel">View All Past Grants for This Beneficiary (<?php echo count($audit['history']); ?> records)</span>
                                <i class="fas fa-chevron-down" id="auditHistoryChevron"></i>
                            </button>
                            <span style="font-size: 0.74rem; color: var(--text-muted);">
                                90-Day Total Disbursed: <strong>₱<?php echo number_format($audit['total_90d_amount'], 2); ?></strong>
                            </span>
                        </div>

                        <!-- Past Assistance Table Accordion -->
                        <div id="auditHistoryTableWrap" style="display: none; margin-top: 14px; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card);">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.76rem; text-align: left;">
                                <thead>
                                    <tr style="background: var(--bg-primary); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase;">
                                        <th style="padding: 8px 12px;">Tracking</th>
                                        <th style="padding: 8px 12px;">Type</th>
                                        <th style="padding: 8px 12px;">Date</th>
                                        <th style="padding: 8px 12px;">Days Ago</th>
                                        <th style="padding: 8px 12px;">Amount</th>
                                        <th style="padding: 8px 12px;">Status</th>
                                        <th style="padding: 8px 12px; text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit['history'] as $h): 
                                        $h_track = 'AICS-' . str_pad($h['aics_id'], 6, '0', STR_PAD_LEFT);
                                        $h_type = ucwords(str_replace('_', ' ', $h['request_type']));
                                        $h_date = date('M d, Y', strtotime($h['effective_date']));
                                        $h_amt = floatval($h['amount_approved'] ?: $h['amount_requested']);
                                        $h_is_within = $h['is_within_90'];
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--border-color); <?php echo $h_is_within ? 'background: rgba(239,68,68,0.03);' : ''; ?>">
                                        <td style="padding: 8px 12px; font-weight: 700;">
                                            <a href="view-aics.php?id=<?php echo $h['aics_id']; ?>" target="_blank" style="color: var(--maroon); text-decoration: none;">
                                                <?php echo $h_track; ?>
                                            </a>
                                        </td>
                                        <td style="padding: 8px 12px;"><?php echo htmlspecialchars($h_type); ?></td>
                                        <td style="padding: 8px 12px;"><?php echo $h_date; ?></td>
                                        <td style="padding: 8px 12px;">
                                            <?php if ($h_is_within): ?>
                                                <span style="font-weight: 700; color: #DC2626;"><?php echo $h['days_ago']; ?>d ago (within 90d)</span>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);"><?php echo $h['days_ago']; ?>d ago</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 8px 12px; font-weight: 600;">₱<?php echo number_format($h_amt, 2); ?></td>
                                        <td style="padding: 8px 12px;">
                                            <span class="status-badge <?php echo htmlspecialchars($h['status']); ?>" style="font-size: 0.65rem; padding: 2px 6px;">
                                                <?php echo ucfirst($h['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px 12px; text-align: right;">
                                            <a href="view-aics.php?id=<?php echo $h['aics_id']; ?>" target="_blank" style="color: var(--maroon); font-size: 0.72rem; text-decoration: underline;">
                                                Inspect <i class="fas fa-external-link-alt" style="font-size: 0.65rem;"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <script>
            function toggleAuditHistoryTable() {
                var el = document.getElementById('auditHistoryTableWrap');
                var chevron = document.getElementById('auditHistoryChevron');
                if (el.style.display === 'none' || el.style.display === '') {
                    el.style.display = 'block';
                    if (chevron) chevron.className = 'fas fa-chevron-up';
                } else {
                    el.style.display = 'none';
                    if (chevron) chevron.className = 'fas fa-chevron-down';
                }
            }
            </script>
            <?php
            return ob_get_clean();
        }

        return '';
    }
}
