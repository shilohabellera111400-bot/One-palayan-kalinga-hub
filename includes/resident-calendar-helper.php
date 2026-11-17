<?php
/**
 * includes/resident-calendar-helper.php
 * Interactive Resident Activity and Appointment Calendar Helper
 * Handles Yellow (Personal appointments & applications) and Red (Announcements & Service schedules)
 */

if (!function_exists('getResidentCalendarMatrix')) {
    function getResidentCalendarMatrix($db, $user_id, $cal_month = null, $cal_year = null): array {
        if ($cal_month === null) $cal_month = (int)date('n');
        if ($cal_year === null) $cal_year = (int)date('Y');
        return getResidentCalendarData($db, $user_id, $cal_month, $cal_year);
    }
}

if (!function_exists('getResidentCalendarData')) {
    function getResidentCalendarData($db, $user_id, $cal_month = null, $cal_year = null): array {
        if ($cal_month === null) $cal_month = (int)date('n');
        if ($cal_year === null) $cal_year = (int)date('Y');
        if ($cal_month < 1) { $cal_month = 12; $cal_year--; }
        if ($cal_month > 12) { $cal_month = 1; $cal_year++; }

        $cal_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
        $days_in_month = (int)date('t', strtotime($cal_start));
        $cal_end = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $days_in_month);
        $first_dow = (int)date('N', strtotime($cal_start)); // 1=Mon, 7=Sun

        $activities = [];
        for ($d = 1; $d <= $days_in_month; $d++) {
            $d_str = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
            $activities[$d_str] = [
                'day' => $d,
                'date' => $d_str,
                'has_yellow' => false,
                'has_red' => false,
                'has_blackout' => false,
                'personal' => [],
                'announcements' => []
            ];
        }

        // 1. YELLOW INDICATORS: Appointments & Service Applications
        try {
            // Appointments
            $stmt = $db->prepare("SELECT a.*, s.service_name 
                                  FROM appointments a 
                                  JOIN services s ON a.service_id = s.service_id 
                                  WHERE a.user_id = :uid AND a.appointment_date BETWEEN :start_d AND :end_d 
                                  ORDER BY a.appointment_time ASC");
            $stmt->execute([':uid' => $user_id, ':start_d' => $cal_start, ':end_d' => $cal_end]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dt = $row['appointment_date'];
                if (isset($activities[$dt])) {
                    $activities[$dt]['has_yellow'] = true;
                    $activities[$dt]['personal'][] = [
                        'type' => 'appointment',
                        'title' => 'Appointment: ' . $row['service_name'],
                        'ref' => $row['reference_number'] ?? ('APT-' . $row['appointment_id']),
                        'time' => date('h:i A', strtotime($row['appointment_time'])),
                        'status' => $row['status'],
                        'details' => (!empty($row['window_assignment']) ? $row['window_assignment'] : 'CSWDO Desk') . ($row['purpose'] ? ' &bull; ' . htmlspecialchars($row['purpose']) : ''),
                        'link' => 'my-appointments.php'
                    ];
                }
            }

            // AICS Requests
            $stmt = $db->prepare("SELECT aics_id, request_type, status, purpose, DATE(submission_date) as s_date 
                                  FROM aics_requests 
                                  WHERE user_id = :uid AND DATE(submission_date) BETWEEN :start_d AND :end_d");
            $stmt->execute([':uid' => $user_id, ':start_d' => $cal_start, ':end_d' => $cal_end]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dt = $row['s_date'];
                if (isset($activities[$dt])) {
                    $activities[$dt]['has_yellow'] = true;
                    $activities[$dt]['personal'][] = [
                        'type' => 'aics',
                        'title' => 'AICS Application: ' . ucfirst(str_replace('_', ' ', $row['request_type'] ?? 'Assistance')),
                        'ref' => 'AICS-' . str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT),
                        'time' => 'Application Filed',
                        'status' => $row['status'],
                        'details' => $row['purpose'] ? htmlspecialchars($row['purpose']) : 'Crisis assistance application',
                        'link' => 'track-request.php?ref=' . urlencode('AICS-' . str_pad($row['aics_id'], 6, '0', STR_PAD_LEFT))
                    ];
                }
            }

            // Housing Applications
            $stmt = $db->prepare("SELECT beneficiary_id, housing_type, status, application_date 
                                  FROM housing_beneficiaries 
                                  WHERE user_id = :uid AND application_date BETWEEN :start_d AND :end_d");
            $stmt->execute([':uid' => $user_id, ':start_d' => $cal_start, ':end_d' => $cal_end]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dt = $row['application_date'];
                if (isset($activities[$dt])) {
                    $activities[$dt]['has_yellow'] = true;
                    $activities[$dt]['personal'][] = [
                        'type' => 'housing',
                        'title' => 'Housing Application: ' . ucfirst($row['housing_type'] ?? 'Relocation'),
                        'ref' => 'HSG-2026-' . str_pad($row['beneficiary_id'], 5, '0', STR_PAD_LEFT),
                        'time' => 'Application Filed',
                        'status' => $row['status'],
                        'details' => 'Socialized housing beneficiary application',
                        'link' => 'track-request.php?ref=' . urlencode('HSG-2026-' . str_pad($row['beneficiary_id'], 5, '0', STR_PAD_LEFT))
                    ];
                }
            }

            // 3. BLACKOUT DATES / HOLIDAYS (Official Non-Working Days)
            $stmt = $db->prepare("SELECT * FROM blackout_dates 
                                  WHERE blackout_date BETWEEN :start_d AND :end_d 
                                  ORDER BY blackout_date ASC");
            $stmt->execute([':start_d' => $cal_start, ':end_d' => $cal_end]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dt = $row['blackout_date'];
                if (isset($activities[$dt])) {
                    $activities[$dt]['has_blackout'] = true;
                    $activities[$dt]['has_red'] = true;
                    $activities[$dt]['announcements'][] = [
                        'type' => 'blackout',
                        'title' => 'Walang Pasok: ' . ($row['reason'] ?? 'CSWDO Blackout Date'),
                        'category' => 'Official Blackout Date',
                        'details' => 'Opisyal na non-working day sa Palayan City CSWDO (' . htmlspecialchars($row['reason']) . '). Sarado ang appointment bookings.',
                        'link' => 'calendar.php'
                    ];
                }
            }
        } catch (Exception $e) {}

        // 2. RED INDICATORS: Service Announcements & Important Schedules
        try {
            // Announcements
            $stmt = $db->prepare("SELECT announcement_id, title, category, content, DATE(created_at) as a_date 
                                  FROM announcements 
                                  WHERE DATE(created_at) BETWEEN :start_d AND :end_d 
                                  ORDER BY created_at DESC");
            $stmt->execute([':start_d' => $cal_start, ':end_d' => $cal_end]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dt = $row['a_date'];
                if (isset($activities[$dt])) {
                    $activities[$dt]['has_red'] = true;
                    $activities[$dt]['announcements'][] = [
                        'type' => 'announcement',
                        'title' => $row['title'],
                        'category' => ucfirst($row['category'] ?? 'General Announcement'),
                        'details' => substr(strip_tags($row['content']), 0, 140),
                        'link' => 'announcements.php'
                    ];
                }
            }

            // Calendar Events (Claiming schedules, application deadlines, desk hours)
            $stmt = $db->prepare("SELECT * FROM calendar_events 
                                  WHERE event_date BETWEEN :start_d AND :end_d 
                                  ORDER BY start_time ASC");
            $stmt->execute([':start_d' => $cal_start, ':end_d' => $cal_end]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dt = $row['event_date'];
                if (isset($activities[$dt])) {
                    $activities[$dt]['has_red'] = true;
                    $time_fmt = !empty($row['start_time']) ? date('h:i A', strtotime($row['start_time'])) : 'Scheduled Date';
                    if (!empty($row['end_time'])) {
                        $time_fmt .= ' - ' . date('h:i A', strtotime($row['end_time']));
                    }
                    $activities[$dt]['announcements'][] = [
                        'type' => 'event',
                        'title' => $row['title'],
                        'category' => 'Official Schedule Update',
                        'details' => ($row['description'] ?? 'City service schedule') . ' (' . $time_fmt . ')',
                        'link' => 'announcements.php'
                    ];
                }
            }
        } catch (Exception $e) {}

        return [
            'month' => $cal_month,
            'year' => $cal_year,
            'month_name' => date('F', strtotime($cal_start)),
            'days_in_month' => $days_in_month,
            'first_dow' => $first_dow,
            'activities' => $activities
        ];
    }
}
