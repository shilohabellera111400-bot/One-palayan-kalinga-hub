<?php
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$database = new Database();
$db = $database->getConnection();

// Get available services
$services_query = "SELECT * FROM services WHERE is_active = 1";
$services_stmt = $db->prepare($services_query);
$services_stmt->execute();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $service_id = sanitize($_POST['service_id']);
    $appointment_date = sanitize($_POST['appointment_date']);
    $appointment_time = sanitize($_POST['appointment_time']);
    $purpose = sanitize($_POST['purpose']);
    
    // Validate date (cannot be in the past)
    if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $error = "Appointment date cannot be in the past.";
    }
    // Check daily and slot availability
    else {
        // Fetch max appointments limit setting
        $limit_stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'max_appointments_per_day'");
        $limit_stmt->execute();
        $limit_row = $limit_stmt->fetch(PDO::FETCH_ASSOC);
        $max_per_day = intval($limit_row['setting_value'] ?? 15);

        // Check daily limit
        $daily_check_query = "SELECT COUNT(*) as count FROM appointments 
                              WHERE appointment_date = :date AND status != 'cancelled'";
        $daily_check_stmt = $db->prepare($daily_check_query);
        $daily_check_stmt->bindParam(':date', $appointment_date);
        $daily_check_stmt->execute();
        $daily_result = $daily_check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($daily_result['count'] >= $max_per_day) {
            $error = "Patawad, ang araw na ito ay puno na ng appointment. Mangyaring pumili ng ibang petsa.";
        } else {
            $check_query = "SELECT COUNT(*) as count FROM appointments 
                            WHERE appointment_date = :date AND appointment_time = :time 
                            AND status != 'cancelled'";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':date', $appointment_date);
            $check_stmt->bindParam(':time', $appointment_time);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] >= 5) { // Limit 5 appointments per time slot
                $error = "This time slot is fully booked. Please choose another time.";
            } else {
                // Create appointment
                $query = "INSERT INTO appointments (user_id, service_id, appointment_date, appointment_time, purpose, status) 
                          VALUES (:user_id, :service_id, :date, :time, :purpose, 'pending')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':service_id', $service_id);
                $stmt->bindParam(':date', $appointment_date);
                $stmt->bindParam(':time', $appointment_time);
                $stmt->bindParam(':purpose', $purpose);
                
                if ($stmt->execute()) {
                    $appointment_id = $db->lastInsertId();
                    
                    // Create notification
                    $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                                    VALUES (:user_id, 'Appointment Confirmed', 
                                    'Your appointment has been scheduled for " . date('F d, Y', strtotime($appointment_date)) . " at " . date('h:i A', strtotime($appointment_time)) . "', 'appointment')";
                    $notif_stmt = $db->prepare($notif_query);
                    $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
                    $notif_stmt->execute();
                    
                    $success = "Appointment booked successfully! Please upload your requirements.";
                    
                    // Redirect to document upload after 2 seconds
                    header("refresh:2;url=upload-documents.php?appointment_id=" . $appointment_id);
                } else {
                    $error = "Failed to book appointment. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - One Palayan-Kalinga Hub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/portal-layout.css?v=<?php echo filemtime(__DIR__ . '/css/portal-layout.css'); ?>">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/portal-sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/includes/portal-header.php'; ?>

            <div class="portal-page-subheader">
                <div class="portal-page-subheader-title">
                    <h2>Book an Appointment</h2>
                    <p>Schedule your in-person visit with Palayan City government offices</p>
                </div>
                <div class="portal-page-subheader-actions">
                    <a href="appointments.php" class="portal-btn-sm portal-btn-outline"><i class="fas fa-calendar-alt"></i> Appointment Desk</a>
                    <a href="my-appointments.php" class="portal-btn-sm portal-btn-primary"><i class="fas fa-calendar-check"></i> My Appointments</a>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="booking-form">
                <form method="POST" action="" id="bookingForm">
                    <?php echo csrf_field(); ?>
                    <div class="form-group">
                        <label for="service_id">Select Service *</label>
                        <select id="service_id" name="service_id" required>
                            <option value="">-- Choose a service --</option>
                            <?php while($service = $services_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $service['service_id']; ?>">
                                <?php echo htmlspecialchars($service['service_name']); ?>
                                <?php if(!empty($service['processing_time'])): ?>
                                    (<?php echo htmlspecialchars($service['processing_time']); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="appointment_date">Preferred Date *</label>
                            <input type="date" id="appointment_date" name="appointment_date" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                   max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_time">Preferred Time *</label>
                            <select id="appointment_time" name="appointment_time" required>
                                <option value="">-- Select time --</option>
                                <option value="09:00:00">9:00 AM</option>
                                <option value="10:00:00">10:00 AM</option>
                                <option value="11:00:00">11:00 AM</option>
                                <option value="13:00:00">1:00 PM</option>
                                <option value="14:00:00">2:00 PM</option>
                                <option value="15:00:00">3:00 PM</option>
                                <option value="16:00:00">4:00 PM</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose / Additional Notes</label>
                        <textarea id="purpose" name="purpose" rows="4" 
                                  placeholder="Please provide any additional information about your appointment..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn-primary">Book Appointment</button>
                        <a href="dashboard.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            
            <!-- Available Slots Display -->
            <div class="available-slots">
                <h3>Available Time Slots</h3>
                <p class="hint">Select a date above to see available slots</p>
                <div id="slots-container" class="slots-grid">
                    <!-- Will be populated via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Load blackout dates and disable them in the date picker
    const dateInput = document.getElementById('appointment_date');
    let blackoutDates = [];

    fetch('get-blackout-dates.php')
        .then(r => r.json())
        .then(data => {
            blackoutDates = (data.blackout_dates || []).map(d => d.date);
            const blackoutMap = {};
            (data.blackout_dates || []).forEach(d => { blackoutMap[d.date] = d.reason; });
            window._blackoutMap = blackoutMap;
        });

    dateInput.addEventListener('change', function () {
        const date = this.value;

        // Client-side blackout check
        if (blackoutDates.includes(date)) {
            document.getElementById('slots-container').innerHTML =
                `<div class="blackout-notice"><i class="fas fa-ban"></i> <strong>Hindi available ang petsang ito:</strong> ${window._blackoutMap[date] || 'Holiday / Non-working day'}. Mangyaring pumili ng ibang petsa.</div>`;
            return;
        }

        const slotsContainer = document.getElementById('slots-container');
        if (!date) {
            slotsContainer.innerHTML = '<p class="hint">Please select a date first</p>';
            return;
        }
        
        // Show loading
        slotsContainer.innerHTML = '<p class="hint">Loading available slots...</p>';
        
        fetch(`get-available-slots.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                slotsContainer.innerHTML = '';

                // Handle blackout from server too
                if (data.is_blackout) {
                    slotsContainer.innerHTML = `<div class="blackout-notice"><i class="fas fa-ban"></i> <strong>Hindi available:</strong> ${data.reason}. Mangyaring pumili ng ibang petsa.</div>`;
                    return;
                }
                
                if (data.slots && data.slots.length > 0) {
                    data.slots.forEach(slot => {
                        const slotElement = document.createElement('div');
                        slotElement.className = 'slot-card ' + (slot.available ? 'available' : 'booked');
                        slotElement.innerHTML = `
                            <div class="slot-time">${slot.time}</div>
                            <div class="slot-status">
                                ${slot.available ? 
                                    `<span class="available-badge">✓ Available (${slot.remaining}/${slot.max} slots)</span>` : 
                                    '<span class="booked-badge">✗ Fully Booked</span>'}
                            </div>
                        `;
                        
                        // Make available slots clickable
                        if (slot.available) {
                            slotElement.style.cursor = 'pointer';
                            slotElement.addEventListener('click', function() {
                                document.getElementById('appointment_time').value = slot.value;
                                
                                // Highlight selected slot
                                document.querySelectorAll('.slot-card').forEach(c => {
                                    c.classList.remove('selected');
                                });
                                slotElement.classList.add('selected');
                            });
                        }
                        
                        slotsContainer.appendChild(slotElement);
                    });
                } else {
                    slotsContainer.innerHTML = '<p class="hint">No slots available for this date</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                slotsContainer.innerHTML = '<p class="error">Error loading slots. Please try again.</p>';
            });
    });
    
    // Trigger change on page load if date is already selected
    document.addEventListener('DOMContentLoaded', function() {
        if (dateInput.value) {
            dateInput.dispatchEvent(new Event('change'));
        }
    });
    </script>

    
    <style>
    .booking-form {
        background: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        color: #333;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 1rem;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #003366;
    }
    
    .available-slots {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .slots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .slot-card {
        padding: 15px;
        border-radius: 5px;
        text-align: center;
        transition: all 0.3s;
    }
    
    .slot-card.available {
        background: #e8f5e8;
        border: 1px solid #4CAF50;
    }
    
    .slot-card.booked {
        background: #ffebee;
        border: 1px solid #ff4444;
        opacity: 0.6;
    }
    
    .slot-card.selected {
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(0,51,102,0.2);
        border: 2px solid #003366;
    }
    
    .slot-time {
        font-weight: bold;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }
    
    .available-badge {
        color: #4CAF50;
        font-size: 0.9rem;
    }
    
    .booked-badge {
        color: #ff4444;
        font-size: 0.9rem;
    }
    
    .hint {
        color: #666;
        font-size: 0.9rem;
        font-style: italic;
        margin: 10px 0;
    }
    
    .blackout-notice {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffcc80;
        border-radius: 8px;
        padding: 16px 20px;
        font-size: 0.92rem;
        display: flex;
        align-items: center;
        gap: 10px;
        grid-column: 1 / -1;
    }
    .blackout-notice i { font-size: 1.2rem; }

    
    .alert {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .alert-error {
        background: #ffebee;
        color: #ff4444;
        border: 1px solid #ffcdd2;
    }
    
    .alert-success {
        background: #e8f5e8;
        color: #4CAF50;
        border: 1px solid #c8e6c9;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        
        .slots-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <script src="js/main.js"></script>
    <?php include __DIR__ . '/includes/portal-search-modal.php'; ?>
</body>
</html>