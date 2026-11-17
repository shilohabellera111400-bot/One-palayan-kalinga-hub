<?php
// includes/appointment-helper.php
// Centralized helper functions for LGU Appointment Management System

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Mailer.php';

/**
 * Generate a unique Appointment Reference Number: PAL-SOC-YYYY-000123
 */
function generateAppointmentRefNumber(PDO $db): string {
    $year = date('Y');
    
    // Get next ID count for the year
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE YEAR(created_at) = :year");
    $stmt->execute([':year' => $year]);
    $next_num = ((int)$stmt->fetchColumn()) + 1;
    
    do {
        $ref = sprintf("PAL-SOC-%s-%06d", $year, $next_num);
        $check = $db->prepare("SELECT appointment_id FROM appointments WHERE reference_number = :ref LIMIT 1");
        $check->execute([':ref' => $ref]);
        if ($check->rowCount() == 0) {
            return $ref;
        }
        $next_num++;
    } while (true);
}

/**
 * Generate a secure QR code identification token (does not leak personal info)
 */
function generateQrToken(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Log appointment action in the audit trail (appointment_status_history)
 */
function logAppointmentHistory(PDO $db, int $appointment_id, string $action_or_status, $arg4 = null, $arg5 = null, ?int $performed_by = null, string $role = 'system', string $remarks = ''): bool {
    try {
        // If called as logAppointmentHistory($db, $id, $status, $notes, $user_id)
        if (!is_string($arg4) || (is_string($arg4) && $arg5 === null && !in_array($arg4, ['pending','for_review','confirmed','checked_in','in_progress','completed','cancelled','rejected','no_show','needs_information','rescheduled']))) {
            $new_status = $action_or_status;
            $action = 'STATUS_UPDATE';
            $prev_status = null;
            $remarks = is_string($arg4) ? $arg4 : '';
            if (is_int($arg5)) {
                $performed_by = $arg5;
            }
        } else {
            $action = $action_or_status;
            $prev_status = is_string($arg4) ? $arg4 : null;
            $new_status = is_string($arg5) ? $arg5 : $action_or_status;
        }

        if ($performed_by === null && isset($_SESSION['user_id'])) {
            $performed_by = (int)$_SESSION['user_id'];
            $role = isStaff() ? 'staff' : (isAdmin() ? 'admin' : 'resident');
        }

        $stmt = $db->prepare("INSERT INTO appointment_status_history 
            (appointment_id, action, previous_status, new_status, performed_by, performer_role, remarks) 
            VALUES (:aid, :action, :prev, :new, :uid, :role, :remarks)");
        return $stmt->execute([
            ':aid'     => $appointment_id,
            ':action'  => $action,
            ':prev'    => $prev_status,
            ':new'     => $new_status,
            ':uid'     => $performed_by,
            ':role'    => $role,
            ':remarks' => $remarks
        ]);
    } catch (Exception $e) {
        error_log("Failed to log appointment history: " . $e->getMessage());
        return false;
    }
}

/**
 * Get visual badge HTML for any appointment status
 */
function getAppointmentBadge(string $status): string {
    $statuses = [
        'pending' => [
            'label' => 'Pending Review',
            'bg'    => '#FEF3C7',
            'color' => '#92400E',
            'border'=> '#FCD34D',
            'icon'  => 'fa-clock'
        ],
        'for_review' => [
            'label' => 'For Review',
            'bg'    => '#DBEAFE',
            'color' => '#1E40AF',
            'border'=> '#93C5FD',
            'icon'  => 'fa-search'
        ],
        'confirmed' => [
            'label' => 'Confirmed',
            'bg'    => '#D1FAE5',
            'color' => '#065F46',
            'border'=> '#6EE7B7',
            'icon'  => 'fa-check-circle'
        ],
        'rescheduled' => [
            'label' => 'Rescheduled',
            'bg'    => '#EDE9FE',
            'color' => '#5B21B6',
            'border'=> '#C4B5FD',
            'icon'  => 'fa-calendar-alt'
        ],
        'checked_in' => [
            'label' => 'Checked-In',
            'bg'    => '#CFFAFE',
            'color' => '#155E75',
            'border'=> '#67E8F9',
            'icon'  => 'fa-user-check'
        ],
        'in_progress' => [
            'label' => 'In Progress',
            'bg'    => '#FFEDD5',
            'color' => '#9A3412',
            'border'=> '#FDBA74',
            'icon'  => 'fa-spinner fa-spin'
        ],
        'completed' => [
            'label' => 'Completed',
            'bg'    => '#E0F2FE',
            'color' => '#0369A1',
            'border'=> '#7DD3FC',
            'icon'  => 'fa-award'
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'bg'    => '#FEE2E2',
            'color' => '#991B1B',
            'border'=> '#FCA5A5',
            'icon'  => 'fa-ban'
        ],
        'rejected' => [
            'label' => 'Rejected',
            'bg'    => '#FFE4E6',
            'color' => '#9F1239',
            'border'=> '#FDA4AF',
            'icon'  => 'fa-times-circle'
        ],
        'no_show' => [
            'label' => 'No-Show',
            'bg'    => '#F3F4F6',
            'color' => '#4B5563',
            'border'=> '#D1D5DB',
            'icon'  => 'fa-user-slash'
        ],
        'needs_information' => [
            'label' => 'Needs Info',
            'bg'    => '#FEF9C3',
            'color' => '#854D0E',
            'border'=> '#FDE047',
            'icon'  => 'fa-question-circle'
        ],
    ];

    $s = $statuses[$status] ?? [
        'label' => ucfirst(str_replace('_', ' ', $status)),
        'bg'    => '#F3F4F6',
        'color' => '#374151',
        'border'=> '#E5E7EB',
        'icon'  => 'fa-info-circle'
    ];

    return sprintf(
        '<span class="appt-badge" style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:50px;font-size:0.75rem;font-weight:700;background:%s;color:%s;border:1px solid %s;letter-spacing:0.02em;white-space:nowrap;"><i class="fas %s"></i> %s</span>',
        $s['bg'],
        $s['color'],
        $s['border'],
        $s['icon'],
        htmlspecialchars($s['label'])
    );
}

/**
 * Get appointment settings from database
 */
function getAppointmentSettings(PDO $db): array {
    $stmt = $db->query("SELECT * FROM appointment_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        $settings = [
            'office_hours_start'    => '08:00:00',
            'office_hours_end'      => '17:00:00',
            'slot_duration_minutes' => 30,
            'max_clients_per_slot'  => 5,
            'max_daily_appointments'=> 80,
            'break_start'           => '12:00:00',
            'break_end'             => '13:00:00',
            'available_days'        => 'Monday,Tuesday,Wednesday,Thursday,Friday'
        ];
    }
    // Normalized aliases
    $settings['start_hour'] = $settings['office_hours_start'] ?? '08:00:00';
    $settings['end_hour'] = $settings['office_hours_end'] ?? '17:00:00';
    $settings['max_capacity_per_slot'] = (int)($settings['max_clients_per_slot'] ?? 5);
    $settings['lunch_break_start'] = $settings['break_start'] ?? '12:00:00';
    $settings['lunch_break_end'] = $settings['break_end'] ?? '13:00:00';
    $settings['operating_days'] = $settings['available_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday';
    $settings['advance_booking_days'] = 30;
    $settings['max_daily_per_user'] = 1;
    return $settings;
}

/**
 * Get list of Social Service Offices/Departments
 */
function getSocialServiceOffices(): array {
    return [
        'Social Services / CSWDO' => [
            'name'     => 'CSWDO - General Social Services',
            'location' => 'Ground Floor, City Hall Building, Palayan City',
            'code'     => 'CSWDO'
        ],
        'AICS Division' => [
            'name'     => 'AICS Division (Crisis Assistance & Relief)',
            'location' => 'CSWDO Crisis Center Window 1-3, Palayan City',
            'code'     => 'AICS'
        ],
        'Senior Citizens Affairs' => [
            'name'     => 'OSCA (Office of Senior Citizens Affairs)',
            'location' => 'OSCA Center, Government Complex, Palayan City',
            'code'     => 'OSCA'
        ],
        'PWD Affairs' => [
            'name'     => 'PDAO (Persons with Disability Affairs Office)',
            'location' => 'PDAO Section, Ground Floor, City Hall, Palayan City',
            'code'     => 'PDAO'
        ],
        'Housing Division' => [
            'name'     => 'Housing & Community Resettlement Division',
            'location' => 'Urban Housing Office, 2nd Floor, Palayan City Hall',
            'code'     => 'HOUSING'
        ]
    ];
}

/**
 * Calculate available time slots for a given date based on capacity & bookings
 */
function getAvailableSlotsForDate(PDO $db, string $date_str, ?int $service_id = null): array {
    $settings = getAppointmentSettings($db);
    $timestamp = strtotime($date_str);
    $day_name = date('l', $timestamp);
    $available_days = array_map('trim', explode(',', $settings['available_days']));

    // Check if day is allowed
    if (!in_array($day_name, $available_days)) {
        return [
            'status'  => 'closed_day',
            'message' => "Office is closed on {$day_name}s.",
            'slots'   => []
        ];
    }

    // Check blackout dates
    $blackout_stmt = $db->prepare("SELECT reason FROM blackout_dates WHERE blackout_date = :date LIMIT 1");
    $blackout_stmt->execute([':date' => $date_str]);
    $blackout = $blackout_stmt->fetch(PDO::FETCH_ASSOC);
    if ($blackout) {
        return [
            'status'  => 'blackout',
            'message' => "Office is unavailable on this date: " . $blackout['reason'],
            'slots'   => []
        ];
    }

    // Check total appointments on this date
    $total_day_stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = :date AND status NOT IN ('cancelled', 'rejected')");
    $total_day_stmt->execute([':date' => $date_str]);
    $total_day_count = (int)$total_day_stmt->fetchColumn();

    if ($total_day_count >= (int)$settings['max_daily_appointments']) {
        return [
            'status'  => 'fully_booked_day',
            'message' => "All appointments for this date are fully booked.",
            'slots'   => []
        ];
    }

    // Get count per time slot on this date
    $booked_stmt = $db->prepare("SELECT appointment_time, COUNT(*) as booked FROM appointments 
                                 WHERE appointment_date = :date 
                                 AND status NOT IN ('cancelled', 'rejected') 
                                 GROUP BY appointment_time");
    $booked_stmt->execute([':date' => $date_str]);
    $booked_map = [];
    while ($row = $booked_stmt->fetch(PDO::FETCH_ASSOC)) {
        $time_key = date('H:i:s', strtotime($row['appointment_time']));
        $booked_map[$time_key] = (int)$row['booked'];
    }

    $start_sec = strtotime($settings['office_hours_start']);
    $end_sec   = strtotime($settings['office_hours_end']);
    $duration_sec = ((int)$settings['slot_duration_minutes']) * 60;
    $break_start = strtotime($settings['break_start']);
    $break_end   = strtotime($settings['break_end']);
    $max_capacity = (int)$settings['max_clients_per_slot'];

    $is_today = (date('Y-m-d') === $date_str);
    $current_time_sec = time();

    $slots = [];
    for ($t = $start_sec; $t < $end_sec; $t += $duration_sec) {
        // Skip lunch break slots
        if ($t >= $break_start && $t < $break_end) {
            continue;
        }

        $time_val = date('H:i:s', $t);
        $time_label = date('h:i A', $t);
        $booked = $booked_map[$time_val] ?? 0;
        $remaining = max(0, $max_capacity - $booked);

        $is_past = ($is_today && ($t <= $current_time_sec + 1800)); // at least 30 mins ahead

        $slot_state = 'available';
        if ($is_past) {
            $slot_state = 'past';
        } elseif ($remaining <= 0) {
            $slot_state = 'fully_booked';
        }

        $slots[] = [
            'time'       => $time_val,
            'label'      => $time_label,
            'booked'     => $booked,
            'capacity'   => $max_capacity,
            'remaining'  => $remaining,
            'status'     => $slot_state
        ];
    }

    return [
        'status' => 'open',
        'slots'  => $slots
    ];
}

/**
 * Dispatch in-app notification and email notification for status transitions
 */
function notifyAppointmentUpdate(PDO $db, int $appointment_id, string $new_status, string $remarks = '', ?int $performed_by = null): bool {
    try {
        // Fetch full appointment with client and service details
        $stmt = $db->prepare("SELECT a.*, s.service_name, u.user_id, u.first_name, u.last_name, u.email, u.contact_number 
                              FROM appointments a
                              LEFT JOIN services s ON a.service_id = s.service_id
                              JOIN users u ON a.user_id = u.user_id
                              WHERE a.appointment_id = :id");
        $stmt->execute([':id' => $appointment_id]);
        $appt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appt) return false;

        $user_id = $appt['user_id'];
        $user_name = trim($appt['first_name'] . ' ' . $appt['last_name']);
        $user_email = $appt['email'];
        $user_phone = $appt['contact_number'] ?? '';
        $ref = $appt['reference_number'] ?: ('PAL-SOC-' . date('Y') . '-' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT));
        $date_fmt = date('F d, Y', strtotime($appt['appointment_date']));
        $time_fmt = date('h:i A', strtotime($appt['appointment_time']));
        $service_name = $appt['service_name'] ?? 'Social Service';

        // Custom notification messages
        $status_messages = [
            'confirmed'   => "Ang inyong appointment ({$ref}) para sa {$service_name} sa {$date_fmt} ({$time_fmt}) ay KUMPIRMADO na. Maaari na ninyong i-download ang inyong Appointment Slip at QR Code.",
            'rescheduled' => "Ang inyong appointment ({$ref}) para sa {$service_name} ay inilipat sa {$date_fmt} ({$time_fmt}). Dahilan: " . ($remarks ?: 'Schedule adjustment.'),
            'rejected'    => "Ang inyong appointment request ({$ref}) para sa {$service_name} ay hindi naaprubahan. Dahilan: " . ($remarks ?: 'Requirements not met.'),
            'cancelled'   => "Ang inyong appointment ({$ref}) para sa {$service_name} ay nakansela.",
            'checked_in'  => "Kayo ay matagumpay na na-check-in para sa inyong appointment ({$ref}). Mangyaring maghintay na tawagin sa tanggapan.",
            'in_progress' => "Kasalukuyan nang sinisimulan ang inyong appointment ({$ref}) para sa {$service_name}.",
            'completed'   => "Natapos na ang inyong appointment ({$ref}) para sa {$service_name}. Maraming salamat po sa inyong pakikipag-ugnayan sa City Government of Palayan!",
            'needs_information' => "Kailangan ng karagdagang impormasyon para sa inyong appointment ({$ref}): " . $remarks
        ];

        $title_map = [
            'confirmed'   => 'Appointment Confirmed ✅',
            'rescheduled' => 'Appointment Rescheduled 📅',
            'rejected'    => 'Appointment Request Rejected ❌',
            'cancelled'   => 'Appointment Cancelled 🚫',
            'checked_in'  => 'Checked-In at Social Service 🎫',
            'in_progress' => 'Appointment Service In Progress ⚡',
            'completed'   => 'Appointment Completed 🎉',
            'needs_information' => 'Additional Information Required 📝'
        ];

        $notif_title = $title_map[$new_status] ?? ("Appointment Update: " . ucfirst(str_replace('_', ' ', $new_status)));
        $notif_msg = $status_messages[$new_status] ?? "Ang inyong appointment ({$ref}) ay na-update sa: " . strtoupper($new_status);

        // In-app notification
        $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :title, :msg, 'appointment')");
        $notif_stmt->execute([
            ':uid'   => $user_id,
            ':title' => $notif_title,
            ':msg'   => $notif_msg
        ]);

        // Email notification dispatch
        if (!empty($user_email)) {
            try {
                $mailer = new Mailer();
                $mailer->sendAppointmentStatusUpdate($user_email, $user_name, $appt, $new_status, $remarks);
            } catch (Exception $mail_err) {
                error_log("Failed sending appointment status email: " . $mail_err->getMessage());
            }
        }

        // SMS notification dispatch via TextBee Gateway
        if (!empty($user_phone)) {
            try {
                require_once __DIR__ . '/SMSGateway.php';
                $sms = new SMSGateway();
                $sms->sendAppointmentStatusUpdate($user_phone, $user_name, $appt, $new_status, $remarks);
            } catch (Throwable $sms_err) {
                error_log("Failed sending appointment status SMS: " . $sms_err->getMessage());
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}
