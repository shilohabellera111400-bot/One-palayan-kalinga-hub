<?php
// admin/cms.php - Dynamic Content Management System (CMS) Hub
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cms-helper.php';
require_once __DIR__ . '/../includes/FileUploader.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$db = (new Database())->getConnection();

$tab = sanitize($_GET['tab'] ?? 'landing');
$error = '';
$success = '';

// Helper for audit logging
function log_cms_audit(PDO $db, string $action, string $table, string $details): void
{
    try {
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, table_affected, new_values) VALUES (:uid, :act, :tbl, :det)");
        $stmt->execute([
            ':uid' => $_SESSION['user_id'] ?? 1,
            ':act' => $action,
            ':tbl' => $table,
            ':det' => $details
        ]);
    } catch (Exception $e) {
        error_log('[CMS Audit Log Error] ' . $e->getMessage());
    }
}

// ─── POST ACTION HANDLERS ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $cms_action = sanitize($_POST['cms_action'] ?? '');

    // 1. Save Landing Page Content
    if ($cms_action === 'save_landing') {
        $fields = [
            'hero_badge' => sanitize($_POST['hero_badge'] ?? ''),
            'hero_headline' => sanitize($_POST['hero_headline'] ?? ''),
            'hero_subheadline' => sanitize($_POST['hero_subheadline'] ?? ''),
            'hero_description' => sanitize($_POST['hero_description'] ?? ''),
            'hero_btn1_text' => sanitize($_POST['hero_btn1_text'] ?? ''),
            'hero_btn1_link' => sanitize($_POST['hero_btn1_link'] ?? ''),
            'hero_btn2_text' => sanitize($_POST['hero_btn2_text'] ?? ''),
            'hero_btn2_link' => sanitize($_POST['hero_btn2_link'] ?? ''),
            'hero_image' => sanitize($_POST['hero_image'] ?? 'images/palayan-city.png'),
            'housing_section_badge' => sanitize($_POST['housing_section_badge'] ?? ''),
            'housing_section_title' => sanitize($_POST['housing_section_title'] ?? ''),
            'housing_section_desc' => sanitize($_POST['housing_section_desc'] ?? ''),
            'housing_section_video' => sanitize($_POST['housing_section_video'] ?? ''),
            'housing_section_poster' => sanitize($_POST['housing_section_poster'] ?? ''),
            'transparency_title' => sanitize($_POST['transparency_title'] ?? ''),
            'transparency_desc' => sanitize($_POST['transparency_desc'] ?? ''),
            'step1_title' => sanitize($_POST['step1_title'] ?? ''),
            'step1_desc' => sanitize($_POST['step1_desc'] ?? ''),
            'step2_title' => sanitize($_POST['step2_title'] ?? ''),
            'step2_desc' => sanitize($_POST['step2_desc'] ?? ''),
            'step3_title' => sanitize($_POST['step3_title'] ?? ''),
            'step3_desc' => sanitize($_POST['step3_desc'] ?? ''),
            'step4_title' => sanitize($_POST['step4_title'] ?? ''),
            'step4_desc' => sanitize($_POST['step4_desc'] ?? ''),
            'doc_upload_badge' => sanitize($_POST['doc_upload_badge'] ?? ''),
            'doc_upload_title' => sanitize($_POST['doc_upload_title'] ?? ''),
            'doc_upload_desc' => sanitize($_POST['doc_upload_desc'] ?? ''),
            'notif_section_title' => sanitize($_POST['notif_section_title'] ?? ''),
            'notif_section_desc' => sanitize($_POST['notif_section_desc'] ?? ''),
            'cta_title' => sanitize($_POST['cta_title'] ?? ''),
            'cta_desc' => sanitize($_POST['cta_desc'] ?? ''),
            'cta_btn1_text' => sanitize($_POST['cta_btn1_text'] ?? ''),
            'cta_btn1_link' => sanitize($_POST['cta_btn1_link'] ?? ''),
            'cta_btn2_text' => sanitize($_POST['cta_btn2_text'] ?? ''),
            'cta_btn2_link' => sanitize($_POST['cta_btn2_link'] ?? '')
        ];

        // Handle hero artwork upload if provided
        if (isset($_FILES['hero_image_file']) && $_FILES['hero_image_file']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUploader();
            try {
                $up_path = $uploader->upload($_FILES['hero_image_file'], 'cms');
                $fields['hero_image'] = $up_path;
            } catch (Exception $e) {
                $error = "Hero Artwork Upload Failed: " . $e->getMessage();
            }
        }

        // Handle housing video file upload if provided
        if (isset($_FILES['housing_video_file']) && $_FILES['housing_video_file']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUploader();
            try {
                $vid_path = $uploader->upload($_FILES['housing_video_file'], 'cms');
                $fields['housing_section_video'] = $vid_path;
            } catch (Exception $e) {
                $error = "Housing Video Upload Failed: " . $e->getMessage();
            }
        }

        // Handle housing poster image upload if provided
        if (isset($_FILES['housing_poster_file']) && $_FILES['housing_poster_file']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUploader();
            try {
                $poster_path = $uploader->upload($_FILES['housing_poster_file'], 'cms');
                $fields['housing_section_poster'] = $poster_path;
            } catch (Exception $e) {
                $error = "Housing Poster Upload Failed: " . $e->getMessage();
            }
        }

        if (empty($error)) {
            foreach ($fields as $k => $v) {
                cms_set($k, $v);
            }
            log_cms_audit($db, 'update_landing_cms', 'cms_content', 'Updated homepage banner and landing content');
            $success = "Landing page visual content updated successfully!";
        }
    }

    // 2. Save Section Visibility Toggles
    elseif ($cms_action === 'save_visibility') {
        $visibility_keys = [
            'show_hero_stats',
            'show_housing_section',
            'show_transparency_section',
            'show_notification_section',
            'show_document_upload_section',
            'show_services_section',
            'show_cta_section'
        ];

        foreach ($visibility_keys as $vk) {
            $val = isset($_POST[$vk]) ? '1' : '0';
            cms_set($vk, $val);
        }

        log_cms_audit($db, 'update_visibility_cms', 'cms_content', 'Updated homepage section visibility controls');
        $success = "Public content visibility settings saved successfully!";
    }

    // 3. Save Website Branding & Contact Information
    elseif ($cms_action === 'save_branding') {
        $branding_fields = [
            'site_title' => sanitize($_POST['site_title'] ?? 'ONE PALAYAN-KALINGA HUB'),
            'site_tagline' => sanitize($_POST['site_tagline'] ?? 'CSWDO Palayan City'),
            'footer_tagline' => sanitize($_POST['footer_tagline'] ?? ''),
            'contact_address' => sanitize($_POST['contact_address'] ?? ''),
            'contact_telephone' => sanitize($_POST['contact_telephone'] ?? ''),
            'contact_mobile' => sanitize($_POST['contact_mobile'] ?? ''),
            'contact_email' => sanitize($_POST['contact_email'] ?? ''),
            'office_hours' => sanitize($_POST['office_hours'] ?? ''),
            'facebook_url' => sanitize($_POST['facebook_url'] ?? '')
        ];

        // Handle logo file upload
        if (isset($_FILES['site_logo_file']) && $_FILES['site_logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUploader();
            try {
                $logo_path = $uploader->upload($_FILES['site_logo_file'], 'cms');
                $branding_fields['site_logo'] = $logo_path;
            } catch (Exception $e) {
                $error = "Logo Upload Failed: " . $e->getMessage();
            }
        }

        if (empty($error)) {
            foreach ($branding_fields as $k => $v) {
                cms_set($k, $v);
            }
            log_cms_audit($db, 'update_branding_cms', 'cms_content', 'Updated branding and contact details');
            $success = "Website branding and contact details saved successfully!";
        }
    }

    // 3.5. Save Core Services & Program Showcase Cards (services.php)
    elseif ($cms_action === 'save_programs') {
        $programs = ['housing', 'aics', 'senior', 'pwd', 'appointments', 'documents'];
        $uploader = new FileUploader();

        $default_slides = [
            'housing'      => ['images/san-lorenzo.png', 'images/housing-sanlorenzo1.png', 'images/housing-sanlorenzo2.png'],
            'aics'         => ['images/Aics1.png', 'images/aics2.png', 'images/aics3.png'],
            'senior'       => ['images/senior2.png', 'images/senior1.png', 'images/senior4.png'],
            'pwd'          => ['images/PWD1.png', 'images/PWD2.png', 'images/PWD3.png'],
            'appointments' => ['images/ap1.png', 'images/ap2.png', 'images/ap3.png'],
            'documents'    => ['images/doc2.png', 'images/doc1.jpg', 'images/doc3.jpg'],
        ];

        foreach ($programs as $prog) {
            $fields = [
                "prog_{$prog}_title"       => sanitize($_POST["prog_{$prog}_title"] ?? ''),
                "prog_{$prog}_badge"       => sanitize($_POST["prog_{$prog}_badge"] ?? ''),
                "prog_{$prog}_caption"     => sanitize($_POST["prog_{$prog}_caption"] ?? ''),
                "prog_{$prog}_desc"        => sanitize($_POST["prog_{$prog}_desc"] ?? ''),
                "prog_{$prog}_subservices" => sanitize($_POST["prog_{$prog}_subservices"] ?? ''),
                "prog_{$prog}_btn1_text"   => sanitize($_POST["prog_{$prog}_btn1_text"] ?? ''),
                "prog_{$prog}_btn2_text"   => sanitize($_POST["prog_{$prog}_btn2_text"] ?? ''),
                "prog_{$prog}_btn2_link"   => sanitize($_POST["prog_{$prog}_btn2_link"] ?? ''),
            ];

            // 1. Collect slides paths submitted in form
            $submitted_slides = $_POST["prog_{$prog}_slides"] ?? [];
            if (!is_array($submitted_slides)) {
                $submitted_slides = [];
            }
            $clean_slides = [];
            foreach ($submitted_slides as $s) {
                $s = trim(sanitize($s));
                // Ignore temporary "[New Upload]" placeholders since their real files will be uploaded below
                if (!empty($s) && strpos($s, '[New Upload]') !== 0) {
                    $clean_slides[] = $s;
                }
            }

            // 2. Handle per-slide replacement uploads
            if (!empty($_FILES["prog_{$prog}_slide_replace"])) {
                $replace_files = $_FILES["prog_{$prog}_slide_replace"];
                if (isset($replace_files['name']) && is_array($replace_files['name'])) {
                    foreach ($replace_files['name'] as $sIdx => $rName) {
                        if (!empty($rName) && $replace_files['error'][$sIdx] === UPLOAD_ERR_OK) {
                            try {
                                $singleFile = [
                                    'name'     => $replace_files['name'][$sIdx],
                                    'type'     => $replace_files['type'][$sIdx],
                                    'tmp_name' => $replace_files['tmp_name'][$sIdx],
                                    'error'    => $replace_files['error'][$sIdx],
                                    'size'     => $replace_files['size'][$sIdx]
                                ];
                                $uploaded_path = $uploader->upload($singleFile, 'cms');
                                if (isset($clean_slides[$sIdx])) {
                                    $clean_slides[$sIdx] = $uploaded_path;
                                } else {
                                    $clean_slides[] = $uploaded_path;
                                }
                            } catch (Exception $e) {
                                $error = "Slide replacement failed for " . ucfirst($prog) . ": " . $e->getMessage();
                            }
                        }
                    }
                }
            }

            // 3. Handle multiple pictures upload for this program
            $multi_key = "prog_{$prog}_slide_files";
            if (isset($_FILES[$multi_key]) && is_array($_FILES[$multi_key]['name'])) {
                foreach ($_FILES[$multi_key]['name'] as $fIdx => $fName) {
                    if (!empty($fName) && $_FILES[$multi_key]['error'][$fIdx] === UPLOAD_ERR_OK) {
                        try {
                            $singleFile = [
                                'name'     => $_FILES[$multi_key]['name'][$fIdx],
                                'type'     => $_FILES[$multi_key]['type'][$fIdx],
                                'tmp_name' => $_FILES[$multi_key]['tmp_name'][$fIdx],
                                'error'    => $_FILES[$multi_key]['error'][$fIdx],
                                'size'     => $_FILES[$multi_key]['size'][$fIdx]
                            ];
                            $uploaded_path = $uploader->upload($singleFile, 'cms');
                            $clean_slides[] = $uploaded_path;
                        } catch (Exception $e) {
                            $error = "Multiple pictures upload failed for " . ucfirst($prog) . ": " . $e->getMessage();
                        }
                    }
                }
            }

            // Fallback if list became completely empty
            if (empty($clean_slides)) {
                $clean_slides = $default_slides[$prog] ?? [];
            }

            // Save JSON encoded slides list
            $clean_slides = array_values(array_unique($clean_slides));
            $fields["prog_{$prog}_slides"] = json_encode($clean_slides);
            // Maintain prog_{prog}_image as cover image for backwards compatibility
            $fields["prog_{$prog}_image"] = $clean_slides[0] ?? '';

            foreach ($fields as $k => $v) {
                if ($v !== '') {
                    cms_set($k, $v);
                }
            }
        }

        if (empty($error)) {
            log_cms_audit($db, 'update_programs_cms', 'cms_content', 'Updated core service program showcase cards & slideshow pictures on services.php');
            $success = "Core program showcase cards and slideshow pictures updated successfully!";
        }
    }

    // 4. Create / Edit Service
    elseif ($cms_action === 'save_service') {
        $service_id = intval($_POST['service_id'] ?? 0);
        $service_name = sanitize($_POST['service_name'] ?? '');
        $service_code = sanitize($_POST['service_code'] ?? '');
        $category = sanitize($_POST['category'] ?? 'General');
        $badge = sanitize($_POST['badge'] ?? '');
        $icon = sanitize($_POST['icon'] ?? 'fas fa-hands-helping');
        $short_desc = sanitize($_POST['short_desc'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $eligibility = sanitize($_POST['eligibility'] ?? '');
        $requirements = sanitize($_POST['requirements'] ?? '');
        $procedure = sanitize($_POST['procedure'] ?? '');
        $processing_time = sanitize($_POST['processing_time'] ?? '1-3 working days');
        $fees = sanitize($_POST['fees'] ?? 'Free of Charge');
        $where_to_apply = sanitize($_POST['where_to_apply'] ?? '');
        $apply_url = sanitize($_POST['apply_url'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;

        if (empty($service_name)) {
            $error = "Service name is required.";
        } else {
            if ($service_id > 0) {
                $stmt = $db->prepare("UPDATE services SET 
                    service_name = :name,
                    service_code = :code,
                    category = :cat,
                    badge = :badge,
                    icon = :icon,
                    short_desc = :sdesc,
                    description = :desc,
                    eligibility = :elig,
                    requirements = :req,
                    `procedure` = :proc,
                    processing_time = :proc_time,
                    fees = :fees,
                    where_to_apply = :where_to,
                    apply_url = :apply_url,
                    sort_order = :sort,
                    is_active = :act,
                    is_featured = :feat
                    WHERE service_id = :id");
                $stmt->execute([
                    ':name' => $service_name,
                    ':code' => $service_code,
                    ':cat' => $category,
                    ':badge' => $badge,
                    ':icon' => $icon,
                    ':sdesc' => $short_desc,
                    ':desc' => $description,
                    ':elig' => $eligibility,
                    ':req' => $requirements,
                    ':proc' => $procedure,
                    ':proc_time' => $processing_time,
                    ':fees' => $fees,
                    ':where_to' => $where_to_apply,
                    ':apply_url' => $apply_url,
                    ':sort' => $sort_order,
                    ':act' => $is_active,
                    ':feat' => $is_featured,
                    ':id' => $service_id
                ]);
                log_cms_audit($db, 'update_service', 'services', "Updated service #$service_id ($service_name)");
                $success = "Service <strong>" . htmlspecialchars($service_name) . "</strong> updated successfully!";
            } else {
                $stmt = $db->prepare("INSERT INTO services (service_name, service_code, category, badge, icon, short_desc, description, eligibility, requirements, `procedure`, processing_time, fees, where_to_apply, apply_url, sort_order, is_active, is_featured) 
                    VALUES (:name, :code, :cat, :badge, :icon, :sdesc, :desc, :elig, :req, :proc, :proc_time, :fees, :where_to, :apply_url, :sort, :act, :feat)");
                $stmt->execute([
                    ':name' => $service_name,
                    ':code' => $service_code,
                    ':cat' => $category,
                    ':badge' => $badge,
                    ':icon' => $icon,
                    ':sdesc' => $short_desc,
                    ':desc' => $description,
                    ':elig' => $eligibility,
                    ':req' => $requirements,
                    ':proc' => $procedure,
                    ':proc_time' => $processing_time,
                    ':fees' => $fees,
                    ':where_to' => $where_to_apply,
                    ':apply_url' => $apply_url,
                    ':sort' => $sort_order,
                    ':act' => $is_active,
                    ':feat' => $is_featured
                ]);
                $new_id = $db->lastInsertId();
                log_cms_audit($db, 'create_service', 'services', "Created new service #$new_id ($service_name)");
                $success = "New service <strong>" . htmlspecialchars($service_name) . "</strong> created successfully!";
            }
        }
    }

    // 5. Toggle Service Status (Active / Inactive)
    elseif ($cms_action === 'toggle_service') {
        $sid = intval($_POST['service_id'] ?? 0);
        if ($sid > 0) {
            $stmt = $db->prepare("UPDATE services SET is_active = IF(is_active = 1, 0, 1) WHERE service_id = :id");
            $stmt->execute([':id' => $sid]);
            log_cms_audit($db, 'toggle_service_status', 'services', "Toggled status for service #$sid");
            $success = "Service status toggled successfully!";
        }
    }

    // 6. Delete Service Safely
    elseif ($cms_action === 'delete_service') {
        $sid = intval($_POST['service_id'] ?? 0);
        if ($sid > 0) {
            $check_app = $db->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = :id");
            $check_app->execute([':id' => $sid]);
            $count = $check_app->fetchColumn();

            if ($count > 0) {
                $db->prepare("UPDATE services SET is_active = 0 WHERE service_id = :id")->execute([':id' => $sid]);
                $success = "Service has $count appointment records associated with it. It has been deactivated rather than deleted to preserve historical data integrity.";
            } else {
                $db->prepare("DELETE FROM services WHERE service_id = :id")->execute([':id' => $sid]);
                log_cms_audit($db, 'delete_service', 'services', "Permanently deleted service #$sid");
                $success = "Service permanently deleted successfully!";
            }
        }
    }

    // 7. Save AICS Documentary Requirement
    elseif ($cms_action === 'save_aics_requirement') {
        $req_id = intval($_POST['req_id'] ?? 0);
        $request_type = sanitize($_POST['request_type'] ?? 'medical');
        $document_name = sanitize($_POST['document_name'] ?? '');
        $document_code = sanitize($_POST['document_code'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 0);

        if (empty($document_name)) {
            $error = "Document name is required.";
        } else {
            if ($req_id > 0) {
                $stmt = $db->prepare("UPDATE aics_document_requirements SET 
                    request_type = :type,
                    document_name = :name,
                    document_code = :code,
                    description = :desc,
                    is_required = :req,
                    sort_order = :sort
                    WHERE req_id = :id");
                $stmt->execute([
                    ':type' => $request_type,
                    ':name' => $document_name,
                    ':code' => $document_code,
                    ':desc' => $description,
                    ':req' => $is_required,
                    ':sort' => $sort_order,
                    ':id' => $req_id
                ]);
                $success = "Requirement <strong>" . htmlspecialchars($document_name) . "</strong> updated successfully!";
            } else {
                if (empty($document_code)) {
                    $document_code = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $document_name));
                }
                $stmt = $db->prepare("INSERT INTO aics_document_requirements (request_type, document_name, document_code, description, is_required, sort_order) 
                    VALUES (:type, :name, :code, :desc, :req, :sort)");
                $stmt->execute([
                    ':type' => $request_type,
                    ':name' => $document_name,
                    ':code' => $document_code,
                    ':desc' => $description,
                    ':req' => $is_required,
                    ':sort' => $sort_order
                ]);
                $success = "New requirement <strong>" . htmlspecialchars($document_name) . "</strong> added successfully!";
            }
            log_cms_audit($db, 'save_aics_requirement', 'aics_document_requirements', "Saved requirement ($document_name) for $request_type");
        }
    }

    // 8. Delete AICS Requirement
    elseif ($cms_action === 'delete_aics_requirement') {
        $req_id = intval($_POST['req_id'] ?? 0);
        if ($req_id > 0) {
            $db->prepare("DELETE FROM aics_document_requirements WHERE req_id = :id")->execute([':id' => $req_id]);
            log_cms_audit($db, 'delete_aics_requirement', 'aics_document_requirements', "Deleted requirement #$req_id");
            $success = "Document requirement deleted successfully!";
        }
    }

    // 9. Save Housing Program Info & Requirements
    elseif ($cms_action === 'save_housing_program') {
        $pid = intval($_POST['program_id'] ?? 0);
        $pname = sanitize($_POST['program_name'] ?? '');
        $pcode = sanitize($_POST['program_code'] ?? '');
        $htype = sanitize($_POST['housing_type'] ?? 'relocation');
        $location = sanitize($_POST['location'] ?? '');
        $units = intval($_POST['total_units'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $requirements = sanitize($_POST['requirements'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');

        if ($pid > 0 && !empty($pname)) {
            $stmt = $db->prepare("UPDATE housing_programs SET 
                program_name = :name,
                program_code = :code,
                housing_type = :htype,
                location = :loc,
                total_units = :units,
                description = :desc,
                requirements = :req,
                status = :status
                WHERE program_id = :id");
            $stmt->execute([
                ':name' => $pname,
                ':code' => $pcode,
                ':htype' => $htype,
                ':loc' => $location,
                ':units' => $units,
                ':desc' => $description,
                ':req' => $requirements,
                ':status' => $status,
                ':id' => $pid
            ]);
            log_cms_audit($db, 'update_housing_program', 'housing_programs', "Updated program #$pid ($pname)");
            $success = "Housing Program <strong>" . htmlspecialchars($pname) . "</strong> updated successfully!";
        }
    }

    // 10. Upload Media to Library
    elseif ($cms_action === 'upload_media') {
        if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
            $category = sanitize($_POST['media_category'] ?? 'general');
            $alt_text = sanitize($_POST['media_alt'] ?? '');
            $uploader = new FileUploader();
            try {
                $file_path = $uploader->upload($_FILES['media_file'], 'media');
                $filename = basename($file_path);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $filesize = filesize(__DIR__ . '/../' . $file_path);

                $dim = '';
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                    $img_info = @getimagesize(__DIR__ . '/../' . $file_path);
                    if ($img_info) {
                        $dim = $img_info[0] . 'x' . $img_info[1];
                    }
                }

                $stmt = $db->prepare("INSERT INTO media_library (file_name, file_path, file_type, file_size, dimensions, alt_text, category, uploaded_by) 
                    VALUES (:name, :path, :type, :size, :dim, :alt, :cat, :uid)");
                $stmt->execute([
                    ':name' => $filename,
                    ':path' => $file_path,
                    ':type' => $_FILES['media_file']['type'] ?? ('image/' . $ext),
                    ':size' => $filesize,
                    ':dim' => $dim,
                    ':alt' => $alt_text,
                    ':cat' => $category,
                    ':uid' => $_SESSION['user_id'] ?? 1
                ]);

                log_cms_audit($db, 'upload_media', 'media_library', "Uploaded asset $filename ($category)");
                $success = "Media file <strong>" . htmlspecialchars($filename) . "</strong> uploaded and registered successfully!";
            } catch (Exception $e) {
                $error = "Upload Failed: " . $e->getMessage();
            }
        } else {
            $error = "Please select a valid file to upload.";
        }
    }

    // 11. Delete Media from Library
    elseif ($cms_action === 'delete_media') {
        $mid = intval($_POST['media_id'] ?? 0);
        if ($mid > 0) {
            $stmt = $db->prepare("SELECT file_path, file_name FROM media_library WHERE media_id = :id");
            $stmt->execute([':id' => $mid]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($media) {
                $full = __DIR__ . '/../' . $media['file_path'];
                if (file_exists($full) && strpos($media['file_path'], 'uploads/') !== false) {
                    @unlink($full);
                }
                $db->prepare("DELETE FROM media_library WHERE media_id = :id")->execute([':id' => $mid]);
                log_cms_audit($db, 'delete_media', 'media_library', "Deleted media #$mid (" . $media['file_name'] . ")");
                $success = "Media file deleted successfully!";
            }
        }
    }
}

// Fetch all CMS content entries
$cms_data = cms_get_all();

// Fetch services
$all_services = cms_get_services(false);

// Fetch AICS requirements
$aics_reqs = cms_get_aics_requirements();

// Fetch Housing programs
$housing_programs = cms_get_housing_programs(false);

// Fetch Media assets
$media_assets = cms_get_media();

// Fetch Announcements for overview
$ann_count = $db->query("SELECT COUNT(*) FROM announcements")->fetchColumn();

// Page Head and Navigation
$page_title = 'Dynamic Content Management System (CMS)';
$active_sidebar = 'cms';
require_once __DIR__ . '/includes/admin-head.php';
?>

<style>
/* ========== MODERN CMS DESIGN SYSTEM ========== */
:root {
    --cms-maroon: #7F0303;
    --cms-maroon-light: #9A1515;
    --cms-maroon-dark: #5A0202;
    --cms-teal: #0F414A;
    --cms-gold: #C9A86C;
    --cms-surface: var(--bg-card, #FFFFFF);
    --cms-canvas: var(--bg-primary, #F8FAFC);
    --cms-border: #E2E8F0;
    --cms-border-focus: #7F0303;
    --cms-radius-lg: 18px;
    --cms-radius-md: 12px;
    --cms-radius-sm: 8px;
    --cms-shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
    --cms-shadow-md: 0 8px 24px rgba(0,0,0,0.08);
    --cms-shadow-hover: 0 12px 32px rgba(127, 3, 3, 0.14);
}

[data-theme="dark"] {
    --cms-border: #2A3A4F;
    --cms-surface: #1A2332;
    --cms-canvas: #0A0E17;
}

/* Modern Primary Button */
.cms-btn-primary {
    background: linear-gradient(135deg, #7F0303 0%, #A81818 100%) !important;
    color: #FFFFFF !important;
    border: none !important;
    padding: 11px 24px !important;
    border-radius: 50px !important;
    font-weight: 700 !important;
    font-size: 0.88rem !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.3) !important;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    text-decoration: none !important;
}
.cms-btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 22px rgba(127, 3, 3, 0.42) !important;
    background: linear-gradient(135deg, #960c0c 0%, #BF2222 100%) !important;
}
.cms-btn-primary:active {
    transform: translateY(0) !important;
}

.cms-btn-secondary {
    background: var(--badge-bg, #F1F5F9) !important;
    color: var(--text-primary, #1E293B) !important;
    border: 1.5px solid var(--cms-border) !important;
    padding: 10px 20px !important;
    border-radius: 50px !important;
    font-weight: 600 !important;
    font-size: 0.85rem !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    transition: all 0.2s ease !important;
    text-decoration: none !important;
}
.cms-btn-secondary:hover {
    background: var(--cms-border) !important;
    transform: translateY(-1px) !important;
}

/* Tab Navigation */
.cms-nav-tabs {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--cms-surface);
    padding: 10px 14px;
    border-radius: var(--cms-radius-md);
    border: 1.5px solid var(--cms-border);
    box-shadow: var(--cms-shadow-sm);
    margin-bottom: 24px;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #CBD5E1 transparent;
}

/* Subtle, clean scrollbar */
.cms-nav-tabs::-webkit-scrollbar {
    height: 5px;
}
.cms-nav-tabs::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
}
.cms-nav-tabs::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 10px;
    transition: background 0.2s ease;
}
.cms-nav-tabs::-webkit-scrollbar-thumb:hover {
    background: #94A3B8;
}

[data-theme="dark"] .cms-nav-tabs::-webkit-scrollbar-thumb {
    background: #334155;
}
[data-theme="dark"] .cms-nav-tabs::-webkit-scrollbar-thumb:hover {
    background: #475569;
}

/* Tab Items */
.cms-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 50px;
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.2;
    color: #475569;
    background: #F8FAFC;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid var(--cms-border);
    flex-shrink: 0;
    white-space: nowrap;
    user-select: none;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
}

.cms-tab-btn i {
    font-size: 0.95rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease;
}

.cms-tab-btn:hover {
    color: var(--cms-maroon, #7F0303);
    background: #FFFFFF;
    border-color: rgba(127, 3, 3, 0.28);
    box-shadow: 0 3px 10px rgba(127, 3, 3, 0.08);
    transform: translateY(-1px);
}

.cms-tab-btn:hover i {
    transform: scale(1.1);
}

.cms-tab-btn.active {
    background: linear-gradient(135deg, #7F0303 0%, #A81818 100%) !important;
    color: #FFFFFF !important;
    border-color: #7F0303 !important;
    box-shadow: 0 4px 14px rgba(127, 3, 3, 0.28) !important;
    transform: translateY(0);
}

.cms-tab-btn.active i {
    color: #FFFFFF !important;
}

/* Distinct Rounded Pill Badges */
.cms-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 20px;
    padding: 0 7px;
    border-radius: 9999px;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    background: #E2E8F0;
    color: #334155;
    border: 1px solid #CBD5E1;
    transition: all 0.2s ease;
}

.cms-tab-btn:hover .cms-tab-badge {
    background: rgba(127, 3, 3, 0.1);
    color: var(--cms-maroon, #7F0303);
    border-color: rgba(127, 3, 3, 0.25);
}

.cms-tab-btn.active .cms-tab-badge {
    background: rgba(255, 255, 255, 0.22) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.35) !important;
    backdrop-filter: blur(4px);
}

[data-theme="dark"] .cms-tab-btn {
    background: rgba(255, 255, 255, 0.04);
    color: #94A3B8;
    border-color: var(--cms-border);
}

[data-theme="dark"] .cms-tab-btn:hover {
    background: rgba(127, 3, 3, 0.18);
    color: #FCA5A5;
    border-color: rgba(248, 113, 113, 0.4);
}

[data-theme="dark"] .cms-tab-badge {
    background: #334155;
    color: #CBD5E1;
    border-color: #475569;
}

[data-theme="dark"] .cms-tab-btn:hover .cms-tab-badge {
    background: rgba(248, 113, 113, 0.2);
    color: #FFFFFF;
    border-color: rgba(248, 113, 113, 0.4);
}

/* Compact Section Accordions / Cards */
.cms-accordion-card {
    background: var(--cms-surface);
    border-radius: var(--cms-radius-lg);
    border: 1.5px solid var(--cms-border);
    box-shadow: var(--cms-shadow-sm);
    margin-bottom: 18px;
    overflow: hidden;
    transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.cms-accordion-card:hover {
    border-color: rgba(127, 3, 3, 0.3);
    box-shadow: var(--cms-shadow-md);
}
.cms-accordion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    cursor: pointer;
    background: var(--cms-surface);
    user-select: none;
    transition: background 0.2s ease;
}
.cms-accordion-header:hover {
    background: rgba(0,0,0,0.015);
}
.cms-acc-title-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}
.cms-acc-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    background: rgba(127, 3, 3, 0.08);
    color: var(--cms-maroon);
}
.cms-acc-text h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
}
.cms-acc-text p {
    margin: 2px 0 0;
    font-size: 0.78rem;
    color: var(--text-muted);
}
.cms-acc-chevron {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.04);
    color: var(--text-muted);
    font-size: 0.85rem;
    transition: transform 0.3s ease, background 0.2s;
}
.cms-accordion-card.active .cms-acc-chevron {
    transform: rotate(180deg);
    background: rgba(127, 3, 3, 0.1);
    color: var(--cms-maroon);
}
.cms-accordion-body {
    padding: 24px;
    border-top: 1px solid var(--cms-border);
    display: block;
}
.cms-accordion-card.collapsed .cms-accordion-body {
    display: none;
}

/* Form inputs & grid */
.cms-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}
.cms-form-grid.grid-3 {
    grid-template-columns: repeat(3, 1fr);
}
.cms-form-full {
    grid-column: 1 / -1;
}

.cms-field {
    margin-bottom: 14px;
}
.cms-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-secondary);
}
.cms-label-tag {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--cms-maroon);
    background: rgba(127, 3, 3, 0.08);
    padding: 1px 7px;
    border-radius: 4px;
}
.cms-input,
.cms-textarea,
.cms-select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--cms-border);
    border-radius: var(--cms-radius-sm);
    font-size: 0.86rem;
    font-family: inherit;
    background: var(--input-bg, #FFFFFF);
    color: var(--text-primary);
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.cms-input:focus,
.cms-textarea:focus,
.cms-select:focus {
    outline: none;
    border-color: var(--cms-maroon);
    box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
}
.cms-help {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 4px;
    display: block;
}

/* Modern Compact 4-Step Workflow Segmented Tabs */
.workflow-step-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    background: rgba(0,0,0,0.03);
    padding: 6px;
    border-radius: 12px;
    border: 1px solid var(--cms-border);
}
.workflow-step-tab {
    flex: 1;
    padding: 9px 12px;
    text-align: center;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-secondary);
    cursor: pointer;
    background: transparent;
    border: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.workflow-step-tab:hover {
    background: rgba(255,255,255,0.6);
}
.workflow-step-tab.active {
    background: var(--cms-surface);
    color: var(--cms-maroon);
    box-shadow: var(--cms-shadow-sm);
}
.wf-step-pane {
    display: none;
    animation: fadeIn 0.25s ease;
}
.wf-step-pane.active {
    display: block;
}

/* Modern Real-time Interactive Hero Preview Card */
.cms-live-preview-panel {
    background: linear-gradient(135deg, #7F0303 0%, #0F414A 100%);
    border-radius: var(--cms-radius-lg);
    padding: 24px;
    color: #FFFFFF;
    margin-bottom: 22px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(15, 65, 74, 0.18);
    border: 1.5px solid rgba(255,255,255,0.12);
}
.cms-live-sync-indicator {
    position: absolute;
    top: 14px;
    right: 18px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 185, 129, 0.25);
    border: 1px solid rgba(16, 185, 129, 0.45);
    color: #6EE7B7;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 50px;
    backdrop-filter: blur(4px);
}
.cms-live-sync-indicator .dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10B981;
    animation: pulseDot 1.5s infinite;
}
@keyframes pulseDot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.6; }
}
.preview-hero-layout {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
}
.preview-hero-left {
    flex: 1;
    min-width: 0;
}
.preview-hero-right {
    width: 140px;
    height: 140px;
    border-radius: 16px;
    overflow: hidden;
    background: rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1.5px solid rgba(255,255,255,0.2);
}
.preview-hero-right img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pv-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 50px;
    background: rgba(255,255,255,0.18);
    font-size: 0.74rem;
    font-weight: 700;
    color: #FFFFFF;
    margin-bottom: 8px;
}
.pv-headline {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.25;
    margin: 0 0 4px;
    color: #FFFFFF;
}
.pv-subheadline {
    font-size: 0.95rem;
    font-weight: 600;
    color: #F6D8A8;
    margin: 0 0 8px;
}
.pv-desc {
    font-size: 0.8rem;
    opacity: 0.9;
    line-height: 1.45;
    margin: 0 0 14px;
}
.pv-buttons {
    display: flex;
    gap: 10px;
}
.pv-btn1 {
    background: #FFFFFF;
    color: #7F0303;
    padding: 7px 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.76rem;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
}
.pv-btn2 {
    background: rgba(255,255,255,0.15);
    color: #FFFFFF;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 7px 16px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.76rem;
}

/* Video Player Showcase Preview */
.cms-video-preview-card {
    background: #000;
    border-radius: 14px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    max-width: 480px;
    border: 1px solid var(--cms-border);
}
.cms-video-preview-card video {
    width: 100%;
    height: 240px;
    object-fit: cover;
    display: block;
}

/* Sticky Bottom Action Bar */
.cms-sticky-save-bar {
    position: sticky;
    bottom: 20px;
    z-index: 999;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--cms-surface);
    padding: 14px 24px;
    border-radius: 50px;
    border: 1.5px solid var(--cms-border);
    box-shadow: 0 12px 36px rgba(0,0,0,0.16);
    margin-top: 24px;
    backdrop-filter: blur(8px);
}
.cms-sticky-save-left {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.86rem;
    color: var(--text-secondary);
}

/* Image preview thumbnails */
.cms-img-thumb-preview {
    width: 80px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--cms-border);
    background: #000;
}

/* Switch styling */
.cms-switch-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background: rgba(0,0,0,0.015);
    border-radius: var(--cms-radius-md);
    border: 1px solid var(--cms-border);
    margin-bottom: 12px;
    transition: all 0.2s ease;
}
.cms-switch-row:hover {
    background: rgba(127, 3, 3, 0.02);
    border-color: rgba(127, 3, 3, 0.2);
}
.cms-switch-info h4 {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 3px;
}
.cms-switch-info p {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin: 0;
}
.switch-wrap {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
}
.switch-wrap input {
    opacity: 0;
    width: 0;
    height: 0;
}
.switch-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: #CBD5E1;
    border-radius: 34px;
    transition: .3s;
}
.switch-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    border-radius: 50%;
    transition: .3s;
}
input:checked + .switch-slider {
    background-color: #10B981;
}
input:checked + .switch-slider:before {
    transform: translateX(22px);
}

/* Modal styling */
.cms-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.65);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.cms-modal.open {
    display: flex;
}
.cms-modal-box {
    background: var(--cms-surface);
    width: 100%;
    max-width: 760px;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: var(--cms-radius-lg);
    padding: 28px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    border: 1px solid var(--cms-border);
    animation: modalPop 0.25s ease-out;
}
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

/* ==========================================================================
   ENHANCED CMS DATA TABLES & TOOLBARS
   ========================================================================== */
.cms-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 16px 22px;
    background: rgba(0, 0, 0, 0.015);
    border-bottom: 1.5px solid var(--cms-border);
    flex-wrap: wrap;
}
.cms-toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 260px;
    flex-wrap: wrap;
}
.cms-search-box {
    position: relative;
    flex: 1;
    max-width: 360px;
    min-width: 200px;
}
.cms-search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 0.9rem;
}
.cms-search-input {
    width: 100%;
    padding: 9px 14px 9px 38px;
    border-radius: 50px;
    border: 1.5px solid var(--cms-border);
    font-size: 0.85rem;
    background: var(--cms-surface);
    color: var(--text-primary);
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.cms-search-input:focus {
    outline: none;
    border-color: var(--cms-maroon);
    box-shadow: 0 0 0 3px rgba(127, 3, 3, 0.12);
}
.cms-filter-select {
    padding: 9px 16px;
    border-radius: 50px;
    border: 1.5px solid var(--cms-border);
    font-size: 0.84rem;
    font-weight: 600;
    background: var(--cms-surface);
    color: var(--text-primary);
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s ease;
}
.cms-filter-select:focus {
    border-color: var(--cms-maroon);
}
.cms-counter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 50px;
    font-size: 0.76rem;
    font-weight: 700;
    background: #F1F5F9;
    color: #475569;
    border: 1px solid var(--cms-border);
}

/* Modern Data Table */
.cms-table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.cms-modern-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.cms-modern-table thead th {
    background: #F8FAFC;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 14px 18px;
    border-bottom: 1.5px solid var(--cms-border);
    white-space: nowrap;
    text-align: left;
}
.cms-modern-table tbody tr {
    transition: background 0.15s ease;
}
.cms-modern-table tbody tr:hover {
    background: rgba(127, 3, 3, 0.02);
}
.cms-modern-table tbody td {
    padding: 14px 18px;
    border-bottom: 1px solid var(--cms-border);
    vertical-align: middle;
    font-size: 0.86rem;
    color: var(--text-primary);
}

/* Service Item Cell */
.cms-service-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.cms-service-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
    background: rgba(127, 3, 3, 0.08);
    color: var(--cms-maroon);
    border: 1px solid rgba(127, 3, 3, 0.15);
}
.cms-service-info-title {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 0.9rem;
    margin: 0 0 2px;
    line-height: 1.3;
}
.cms-service-info-code {
    font-size: 0.74rem;
    color: var(--text-muted);
    font-family: monospace;
}

/* Category Badges */
.cms-cat-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
}
.cms-cat-aics { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
.cms-cat-housing { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }
.cms-cat-senior { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
.cms-cat-pwd { background: #F3E8FF; color: #6B21A8; border: 1px solid #E9D5FF; }
.cms-cat-general { background: #E0F2FE; color: #075985; border: 1px solid #BAE6FD; }

/* Status Toggle Pill */
.cms-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}
.cms-status-pill.active {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.cms-status-pill.inactive {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #CBD5E1;
}
.cms-status-pill:hover {
    transform: scale(1.04);
}
.cms-status-pill .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.cms-status-pill.active .status-dot { background: #10B981; }
.cms-status-pill.inactive .status-dot { background: #94A3B8; }

/* Featured Pill */
.cms-featured-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 50px;
    font-size: 0.74rem;
    font-weight: 700;
}
.cms-featured-pill.yes {
    background: #FFFBEB;
    color: #D97706;
    border: 1px solid #FDE68A;
}
.cms-featured-pill.no {
    color: #94A3B8;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
}

/* Action Button Group */
.cms-actions-group {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
}
.cms-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    border: 1.5px solid transparent;
    transition: all 0.2s ease;
    background: none;
}
.cms-btn-action.edit {
    background: rgba(127, 3, 3, 0.08);
    color: var(--cms-maroon);
    border-color: rgba(127, 3, 3, 0.18);
}
.cms-btn-action.edit:hover {
    background: var(--cms-maroon);
    color: #FFFFFF;
}
.cms-btn-action.delete {
    background: rgba(239, 68, 68, 0.08);
    color: #DC2626;
    border-color: rgba(239, 68, 68, 0.2);
    padding: 6px 10px;
}
.cms-btn-action.delete:hover {
    background: #DC2626;
    color: #FFFFFF;
}

/* ==========================================================================
   MEDIA GALLERY & ASSET UPLOADER (PREVENTS OVERSIZED IMAGES)
   ========================================================================== */
.cms-upload-dropzone {
    border: 2px dashed rgba(127, 3, 3, 0.3);
    background: rgba(127, 3, 3, 0.02);
    border-radius: 14px;
    padding: 24px;
    text-align: center;
    transition: all 0.25s ease;
    cursor: pointer;
    display: block;
}
.cms-upload-dropzone:hover {
    border-color: var(--cms-maroon);
    background: rgba(127, 3, 3, 0.05);
}
.cms-upload-dropzone i.upload-icon {
    font-size: 2.2rem;
    color: var(--cms-maroon);
    margin-bottom: 8px;
    display: inline-block;
}

/* Media Grid Layout: Guaranteed image bounds */
.media-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)) !important;
    gap: 18px !important;
    margin-top: 14px !important;
}
.media-card {
    background: var(--cms-surface);
    border-radius: 12px;
    border: 1.5px solid var(--cms-border);
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
}
.media-card:hover {
    transform: translateY(-3px);
    border-color: rgba(127, 3, 3, 0.35);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
.media-card-thumb-wrap {
    width: 100% !important;
    height: 140px !important;
    max-height: 140px !important;
    position: relative !important;
    overflow: hidden !important;
    background: #0F172A !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-bottom: 1px solid var(--cms-border);
}
.media-card-thumb-wrap img {
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 140px !important;
    object-fit: cover !important;
    display: block !important;
    transition: transform 0.35s ease !important;
}
.media-card:hover .media-card-thumb-wrap img {
    transform: scale(1.06);
}
.media-type-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(15, 23, 42, 0.8);
    color: #FFFFFF;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 4px;
    text-transform: uppercase;
    backdrop-filter: blur(4px);
    letter-spacing: 0.05em;
    z-index: 2;
}
.media-cat-badge-top {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(127, 3, 3, 0.88);
    color: #FFFFFF;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    text-transform: capitalize;
    backdrop-filter: blur(4px);
    z-index: 2;
}
.media-card-body {
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex: 1;
    gap: 8px;
}
.media-card-name {
    font-weight: 700;
    font-size: 0.83rem;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}
.media-card-meta {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.media-card-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding-top: 8px;
    border-top: 1px solid var(--cms-border);
}
.media-btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    background: rgba(0,0,0,0.03);
    border: 1px solid var(--cms-border);
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
}
.media-btn-icon:hover {
    background: var(--cms-maroon);
    color: #FFFFFF;
    border-color: var(--cms-maroon);
}
.media-btn-icon.btn-danger:hover {
    background: #DC2626;
    color: #FFFFFF;
    border-color: #DC2626;
}

/* ==========================================================================
   PUBLIC SECTION VISIBILITY CARDS
   ========================================================================== */
.cms-vis-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    background: var(--cms-surface);
    border: 1.5px solid var(--cms-border);
    border-radius: 14px;
    margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
    gap: 16px;
}
.cms-vis-card:hover {
    border-color: rgba(127, 3, 3, 0.25);
    background: rgba(127, 3, 3, 0.01);
}
.cms-vis-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}
.cms-vis-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(127, 3, 3, 0.08);
    color: var(--cms-maroon);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.cms-vis-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 3px;
    flex-wrap: wrap;
}
.cms-vis-title {
    font-size: 0.96rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.cms-vis-placement-tag {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    background: rgba(0,0,0,0.05);
    color: var(--text-secondary);
    text-transform: uppercase;
}
.cms-vis-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.4;
}
.cms-vis-right {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}
.cms-vis-status-text {
    font-size: 0.78rem;
    font-weight: 700;
    min-width: 110px;
    text-align: right;
    transition: color 0.2s ease;
}
.cms-vis-status-text.visible {
    color: #059669;
}
.cms-vis-status-text.hidden {
    color: #94A3B8;
}

/* Category Filter Pills (Requirements & Media) */
.cms-pill-filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: rgba(0,0,0,0.015);
    border-bottom: 1.5px solid var(--cms-border);
    flex-wrap: wrap;
}
.cms-filter-pill-btn {
    padding: 6px 14px;
    border-radius: 50px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid var(--cms-border);
    background: var(--cms-surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
}
.cms-filter-pill-btn:hover {
    border-color: var(--cms-maroon);
    color: var(--cms-maroon);
}
.cms-filter-pill-btn.active {
    background: var(--cms-maroon);
    color: #FFFFFF;
    border-color: var(--cms-maroon);
    box-shadow: 0 2px 8px rgba(127, 3, 3, 0.25);
}

/* Toast Notification for Copied Path */
.cms-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #0F172A;
    color: #FFFFFF;
    padding: 12px 20px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    gap: 8px;
    z-index: 999999;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
    pointer-events: none;
}
.cms-toast.show {
    opacity: 1;
    transform: translateY(0);
}

/* Program Showcase Live Preview (Exact replica of services.php category-card) */
.cms-prog-editor-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 28px;
    align-items: start;
}
.cms-prog-preview-wrap {
    position: sticky;
    top: 20px;
}
.cms-prog-preview-card {
    background: var(--cms-surface, #FFFFFF);
    border-radius: 20px;
    border: 1.5px solid var(--cms-border);
    overflow: hidden;
    box-shadow: 0 10px 28px rgba(0,0,0,0.06);
}
.cms-prog-pv-banner {
    height: 180px;
    position: relative;
    background-color: #1E293B;
    display: flex;
    align-items: flex-end;
    padding: 16px;
    overflow: hidden;
}
.cms-prog-pv-slideshow {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
}
.cms-prog-pv-slide {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 0.5s ease-in-out, transform 4s ease;
    transform: scale(1);
}
.cms-prog-pv-slide.active {
    opacity: 1;
    transform: scale(1.04);
}
.cms-prog-pv-dots {
    position: absolute;
    bottom: 12px;
    right: 14px;
    z-index: 4;
    display: flex;
    gap: 4px;
}
.cms-prog-pv-dots .pv-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.45);
    cursor: pointer;
    transition: all 0.25s ease;
}
.cms-prog-pv-dots .pv-dot.active {
    background: #E5B338;
    width: 14px;
    border-radius: 8px;
}
.cms-prog-pv-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 5;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.65);
    color: #fff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.62rem;
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s ease;
}
.cms-prog-pv-banner:hover .cms-prog-pv-arrow {
    opacity: 0.85;
}
.cms-prog-pv-arrow:hover {
    opacity: 1 !important;
    background: rgba(127, 3, 3, 0.9);
}
.cms-prog-pv-arrow.prev { left: 8px; }
.cms-prog-pv-arrow.next { right: 8px; }

.cms-slide-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: rgba(0, 0, 0, 0.02);
    border-radius: 12px;
    border: 1.5px solid var(--cms-border);
    transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
}
.cms-slide-row:hover {
    border-color: rgba(127, 3, 3, 0.28) !important;
    background: rgba(127, 3, 3, 0.015) !important;
}
.cms-slide-badge {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--cms-maroon);
    background: rgba(127, 3, 3, 0.08);
    padding: 5px 10px;
    border-radius: 20px;
    white-space: nowrap;
    min-width: 90px;
    text-align: center;
}
.cms-prog-pv-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.78) 100%);
    z-index: 1;
    pointer-events: none;
}
.cms-prog-pv-caption {
    position: relative;
    z-index: 2;
    color: #FFFFFF;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    text-shadow: 0 1px 4px rgba(0,0,0,0.7);
    line-height: 1.3;
}
.cms-prog-pv-content {
    padding: 18px 20px;
}
.cms-prog-pv-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.cms-prog-pv-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(127, 3, 3, 0.08);
    color: var(--cms-maroon);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.cms-prog-pv-title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1.25;
}
.cms-prog-pv-badge {
    font-size: 0.72rem;
    font-weight: 700;
    color: #059669;
    display: block;
    margin-top: 2px;
}
.cms-prog-pv-desc {
    font-size: 0.8rem;
    color: var(--text-muted);
    line-height: 1.45;
    margin: 0 0 12px;
}
.cms-prog-pv-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}
.cms-prog-pv-pill {
    padding: 3px 9px;
    border-radius: 50px;
    background: rgba(0,0,0,0.04);
    color: var(--text-primary);
    font-size: 0.7rem;
    font-weight: 700;
    border: 1px solid var(--cms-border);
}
.cms-prog-pv-buttons {
    display: flex;
    gap: 8px;
}
.cms-prog-pv-btn1 {
    flex: 1;
    background: rgba(0,0,0,0.03);
    color: var(--text-primary);
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-align: center;
    border: 1px solid var(--cms-border);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cms-prog-pv-btn2 {
    flex: 1;
    background: var(--cms-maroon);
    color: #FFFFFF;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-align: center;
    box-shadow: 0 3px 8px rgba(127, 3, 3, 0.25);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Program Pane Toggling */
.prog-editor-pane {
    display: none;
}
.prog-editor-pane.active {
    display: block;
    animation: fadeInPane 0.25s ease-out;
}
@keyframes fadeInPane {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 1024px) {
    .cms-prog-editor-layout { grid-template-columns: 1fr; }
    .cms-prog-preview-wrap { position: static; max-width: 440px; margin: 0 auto 20px; }
}

@media (max-width: 768px) {
    .cms-form-grid, .cms-form-grid.grid-3 { grid-template-columns: 1fr; }
    .preview-hero-layout { flex-direction: column-reverse; align-items: flex-start; }
    .preview-hero-right { width: 100%; height: 120px; }
    .cms-sticky-save-bar { flex-direction: column; gap: 10px; border-radius: 20px; }
    .workflow-step-tabs { flex-direction: column; }
    .cms-toolbar { flex-direction: column; align-items: stretch; }
    .cms-vis-card { flex-direction: column; align-items: flex-start; }
    .cms-vis-right { width: 100%; justify-content: space-between; }
    .media-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important; }
}
</style>

<?php
require_once __DIR__ . '/includes/admin-sidebar.php';
require_once __DIR__ . '/includes/admin-header.php';
?>

<!-- Subheader -->
<div class="dash-subheader">
    <div class="subheader-title-wrap">
        <h1><i class="fas fa-desktop" style="color: var(--ref-maroon);"></i> Content Management System (CMS)</h1>
        <p>Manage all landing page visuals, services, requirements, media, and portal branding dynamically without code changes</p>
    </div>
    <div class="dash-cursive-branding-inline">
        <h2 class="dash-cursive-title">One-Palayan Kalinga Hub</h2>
    </div>
    <div class="subheader-status-wrap">
        <a href="../index.php" target="_blank" class="cms-btn-secondary">
            <i class="fas fa-external-link-alt"></i>
            <span>View Live Site</span>
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-error" style="display:flex; align-items:center; gap:10px; margin-bottom:18px;">
        <i class="fas fa-exclamation-circle" style="font-size:1.3rem;"></i> 
        <span><?php echo strip_tags($error, '<strong><b><br><i>'); ?></span>
    </div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success" style="display:flex; align-items:center; gap:10px; margin-bottom:18px;">
        <i class="fas fa-check-circle" style="font-size:1.3rem;"></i> 
        <span><?php echo strip_tags($success, '<strong><b><br><i>'); ?></span>
    </div>
<?php endif; ?>

<!-- CMS Tab Navigation -->
<div class="cms-nav-tabs" role="tablist" aria-label="CMS Management Sections">
    <a href="?tab=landing" class="cms-tab-btn <?php echo ($tab === 'landing') ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo ($tab === 'landing') ? 'true' : 'false'; ?>">
        <i class="fas fa-home"></i> <span>Landing Page &amp; Hero</span>
    </a>
    <a href="?tab=services" class="cms-tab-btn <?php echo ($tab === 'services') ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo ($tab === 'services') ? 'true' : 'false'; ?>">
        <i class="fas fa-hand-holding-heart"></i> <span>Services Catalog</span>
        <span class="cms-tab-badge"><?php echo count($all_services); ?></span>
    </a>
    <a href="?tab=requirements" class="cms-tab-btn <?php echo ($tab === 'requirements') ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo ($tab === 'requirements') ? 'true' : 'false'; ?>">
        <i class="fas fa-tasks"></i> <span>Requirements &amp; Charter</span>
        <span class="cms-tab-badge"><?php echo count($aics_reqs); ?></span>
    </a>
    <a href="?tab=visibility" class="cms-tab-btn <?php echo ($tab === 'visibility') ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo ($tab === 'visibility') ? 'true' : 'false'; ?>">
        <i class="fas fa-eye"></i> <span>Section Visibility</span>
    </a>
    <a href="?tab=media" class="cms-tab-btn <?php echo ($tab === 'media') ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo ($tab === 'media') ? 'true' : 'false'; ?>">
        <i class="fas fa-photo-video"></i> <span>Media Library</span>
        <span class="cms-tab-badge"><?php echo count($media_assets); ?></span>
    </a>
    <a href="?tab=branding" class="cms-tab-btn <?php echo ($tab === 'branding') ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo ($tab === 'branding') ? 'true' : 'false'; ?>">
        <i class="fas fa-sliders-h"></i> <span>Branding &amp; Contact</span>
    </a>
    <a href="announcements.php" class="cms-tab-btn" role="tab" aria-selected="false">
        <i class="fas fa-bullhorn"></i> <span>Announcements</span>
        <span class="cms-tab-badge"><?php echo $ann_count; ?></span>
    </a>
</div>

<!-- ======================================================================= -->
<!-- TAB 1: LANDING PAGE & HERO BANNER (MODERNIZED ACCORDION & REAL-TIME PREVIEW) -->
<!-- ======================================================================= -->
<?php if ($tab === 'landing'): ?>

    <form method="POST" action="?tab=landing" enctype="multipart/form-data" id="landingForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="cms_action" value="save_landing">

        <!-- Real-Time Interactive Hero Live Preview Card -->
        <div class="cms-live-preview-panel" id="heroLivePreviewPanel">
            <div class="cms-live-sync-indicator">
                <span class="dot"></span>
                <span>Interactive Live Preview</span>
            </div>
            <div class="preview-hero-layout">
                <div class="preview-hero-left">
                    <span class="pv-badge" id="pvBadge"><i class="fas fa-star"></i> <?php echo htmlspecialchars(cms_get('hero_badge', 'CSWDO Palayan City')); ?></span>
                    <h2 class="pv-headline" id="pvHeadline"><?php echo htmlspecialchars(cms_get('hero_headline', 'SERVING PALAYAN CITY WITH EXCELLENCE')); ?></h2>
                    <h3 class="pv-subheadline" id="pvSubheadline"><?php echo htmlspecialchars(cms_get('hero_subheadline', 'Streamlined Social Services For Every Palayan Resident')); ?></h3>
                    <p class="pv-desc" id="pvDesc"><?php echo htmlspecialchars(cms_get('hero_description', 'Schedule appointments, track assistance requests, and access housing programs - all in one integrated platform designed for efficiency and transparency.')); ?></p>
                    <div class="pv-buttons">
                        <span class="pv-btn1" id="pvBtn1"><?php echo htmlspecialchars(cms_get('hero_btn1_text', 'Book Appointment Now')); ?> &rarr;</span>
                        <span class="pv-btn2" id="pvBtn2"><?php echo htmlspecialchars(cms_get('hero_btn2_text', 'Track My Request')); ?></span>
                    </div>
                </div>
                <div class="preview-hero-right">
                    <img id="pvImage" src="../<?php echo ltrim(cms_get('hero_image', 'images/palayan-city.png'), './'); ?>" alt="Artwork Preview" onerror="this.src='../images/logo.png'">
                </div>
            </div>
        </div>

        <!-- Section 1: Hero Banner & Action Buttons -->
        <div class="cms-accordion-card active" id="acc_hero">
            <div class="cms-accordion-header" onclick="toggleAcc('acc_hero')">
                <div class="cms-acc-title-wrap">
                    <div class="cms-acc-icon"><i class="fas fa-rocket"></i></div>
                    <div class="cms-acc-text">
                        <h3>Hero Banner &amp; Welcome Headline</h3>
                        <p>Change badge text, primary headline, subtitle, lead paragraph, and hero artwork</p>
                    </div>
                </div>
                <div class="cms-acc-chevron"><i class="fas fa-chevron-down"></i></div>
            </div>

            <div class="cms-accordion-body">
                <div class="cms-form-grid">
                    <div class="cms-field">
                        <label class="cms-label">Hero Badge Text <span class="cms-label-tag">Pill Tag</span></label>
                        <input type="text" name="hero_badge" id="inp_hero_badge" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_badge', 'CSWDO Palayan City')); ?>" required>
                        <span class="cms-help">Top pill badge displayed with the star icon.</span>
                    </div>

                    <div class="cms-field">
                        <label class="cms-label">Primary Headline <span class="cms-label-tag">All Caps</span></label>
                        <input type="text" name="hero_headline" id="inp_hero_headline" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_headline', 'SERVING PALAYAN CITY WITH EXCELLENCE')); ?>" required>
                        <span class="cms-help">The primary large headline across the hero section.</span>
                    </div>

                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Subheadline Title</label>
                        <input type="text" name="hero_subheadline" id="inp_hero_subheadline" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_subheadline', 'Streamlined Social Services For Every Palayan Resident')); ?>" required>
                    </div>

                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Introductory Paragraph / Lead Description</label>
                        <textarea name="hero_description" id="inp_hero_description" class="cms-textarea" rows="3" required><?php echo htmlspecialchars(cms_get('hero_description', '')); ?></textarea>
                    </div>

                    <div class="cms-field">
                        <label class="cms-label">Primary Button Text</label>
                        <input type="text" name="hero_btn1_text" id="inp_hero_btn1_text" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_btn1_text', 'Book Appointment Now')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Primary Button URL Destination</label>
                        <input type="text" name="hero_btn1_link" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_btn1_link', 'appointments.php')); ?>">
                    </div>

                    <div class="cms-field">
                        <label class="cms-label">Secondary Button Text</label>
                        <input type="text" name="hero_btn2_text" id="inp_hero_btn2_text" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_btn2_text', 'Track My Request')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Secondary Button URL Destination</label>
                        <input type="text" name="hero_btn2_link" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_btn2_link', 'track-aics.php')); ?>">
                    </div>

                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Hero Banner Artwork Image</label>
                        <div style="display:flex; align-items:center; gap:16px; padding:12px; background:rgba(0,0,0,0.02); border-radius:10px; border:1px solid var(--cms-border);">
                            <img id="thumb_hero_image" src="../<?php echo ltrim(cms_get('hero_image', 'images/palayan-city.png'), './'); ?>" class="cms-img-thumb-preview" alt="Thumb" onerror="this.src='../images/logo.png'">
                            <div style="flex:1;">
                                <input type="text" name="hero_image" id="inp_hero_image" class="cms-input" value="<?php echo htmlspecialchars(cms_get('hero_image', 'images/palayan-city.png')); ?>" style="margin-bottom:6px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <label class="cms-btn-secondary" style="font-size:0.75rem; padding:6px 14px; cursor:pointer;">
                                        <i class="fas fa-upload"></i> Upload Replacement Image
                                        <input type="file" name="hero_image_file" id="inp_hero_file" accept="image/*" style="display:none;">
                                    </label>
                                    <span class="cms-help" id="file_hero_name">Supports PNG, JPG, WEBP.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Housing Showcase & Video Uploader (With Video Player Preview) -->
        <div class="cms-accordion-card" id="acc_housing">
            <div class="cms-accordion-header" onclick="toggleAcc('acc_housing')">
                <div class="cms-acc-title-wrap">
                    <div class="cms-acc-icon"><i class="fas fa-video"></i></div>
                    <div class="cms-acc-text">
                        <h3>Housing Showcase &amp; Video Player (Replaces video on index.php)</h3>
                        <p>This video appears in the Housing Program section on the homepage (index.php)</p>
                    </div>
                </div>
                <div class="cms-acc-chevron"><i class="fas fa-chevron-down"></i></div>
            </div>

            <div class="cms-accordion-body">
                <div class="cms-form-grid">
                    <div class="cms-field">
                        <label class="cms-label">Housing Badge Pill</label>
                        <input type="text" name="housing_section_badge" class="cms-input" value="<?php echo htmlspecialchars(cms_get('housing_section_badge', 'Municipal Resettlement and In-Site Housing')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Housing Section Heading</label>
                        <input type="text" name="housing_section_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('housing_section_title', 'Comprehensive Beneficiary Management')); ?>">
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Housing Overview Paragraph</label>
                        <textarea name="housing_section_desc" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('housing_section_desc', '')); ?></textarea>
                    </div>

                    <!-- Video Source & Real Player Preview -->
                    <div class="cms-field">
                        <label class="cms-label">Video File Path or Direct URL</label>
                        <input type="text" name="housing_section_video" id="inp_housing_video" class="cms-input" value="<?php echo htmlspecialchars(cms_get('housing_section_video', 'video/Palayan City Township Housing Fair.mp4')); ?>" style="margin-bottom:8px;">
                        
                        <div style="background:rgba(0,0,0,0.02); padding:12px; border-radius:10px; border:1px solid var(--cms-border);">
                            <label class="cms-btn-secondary" style="font-size:0.78rem; padding:7px 16px; cursor:pointer; margin-bottom:6px;">
                                <i class="fas fa-cloud-upload-alt"></i> Upload New MP4 Video
                                <input type="file" name="housing_video_file" id="inp_housing_vid_file" accept="video/mp4,video/webm" style="display:none;">
                            </label>
                            <span class="cms-help" id="file_vid_name">Upload MP4/WebM directly (up to 100MB).</span>
                        </div>

                        <div style="margin-top:14px;">
                            <label class="cms-label">Video Poster Image Thumbnail</label>
                            <input type="text" name="housing_section_poster" id="inp_housing_poster" class="cms-input" value="<?php echo htmlspecialchars(cms_get('housing_section_poster', 'images/cswdo-housing-assistance.jpg')); ?>" style="margin-bottom:6px;">
                            <label class="cms-btn-secondary" style="font-size:0.75rem; padding:6px 14px; cursor:pointer;">
                                <i class="fas fa-image"></i> Upload Poster Image
                                <input type="file" name="housing_poster_file" accept="image/*" style="display:none;">
                            </label>
                        </div>
                    </div>

                    <!-- Interactive Video Player Preview -->
                    <div class="cms-field" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                        <label class="cms-label" style="width:100%;">Video Live Player Preview on index.php</label>
                        <div class="cms-video-preview-card">
                            <video controls id="previewVideoPlayer" poster="../<?php echo ltrim(cms_get('housing_section_poster', 'images/cswdo-housing-assistance.jpg'), './'); ?>" preload="metadata">
                                <source src="../<?php echo htmlspecialchars(cms_get('housing_section_video', 'video/Palayan City Township Housing Fair.mp4')); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        </div>
                        <span class="cms-help" style="margin-top:6px; text-align:center;">Test playback right here before saving. Plays on index.php in the Housing Section.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Compact 4-Step Transparency Workflow (Compact Segmented Tabs) -->
        <div class="cms-accordion-card" id="acc_transparency">
            <div class="cms-accordion-header" onclick="toggleAcc('acc_transparency')">
                <div class="cms-acc-title-wrap">
                    <div class="cms-acc-icon"><i class="fas fa-stream"></i></div>
                    <div class="cms-acc-text">
                        <h3>Transparency 4-Step Process (Compact Tabs)</h3>
                        <p>Customize the 4 citizen application steps without cluttering the page</p>
                    </div>
                </div>
                <div class="cms-acc-chevron"><i class="fas fa-chevron-down"></i></div>
            </div>

            <div class="cms-accordion-body">
                <div class="cms-form-grid" style="margin-bottom:16px;">
                    <div class="cms-field">
                        <label class="cms-label">Transparency Section Title</label>
                        <input type="text" name="transparency_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('transparency_title', 'Transparency & Accountability')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Section Subtitle</label>
                        <input type="text" name="transparency_desc" class="cms-input" value="<?php echo htmlspecialchars(cms_get('transparency_desc', '')); ?>">
                    </div>
                </div>

                <!-- Segmented Step Switcher -->
                <div class="workflow-step-tabs hero-step-tabs">
                    <button type="button" class="workflow-step-tab active" onclick="switchWfStep(1)">
                        <i class="fas fa-file-alt"></i> Step 1: <?php echo htmlspecialchars(cms_get('step1_title', 'Submit')); ?>
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchWfStep(2)">
                        <i class="fas fa-search"></i> Step 2: <?php echo htmlspecialchars(cms_get('step2_title', 'Review')); ?>
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchWfStep(3)">
                        <i class="fas fa-check-circle"></i> Step 3: <?php echo htmlspecialchars(cms_get('step3_title', 'Approval')); ?>
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchWfStep(4)">
                        <i class="fas fa-hand-holding-usd"></i> Step 4: <?php echo htmlspecialchars(cms_get('step4_title', 'Release')); ?>
                    </button>
                </div>

                <!-- Step 1 Pane -->
                <div class="wf-step-pane active" id="wf_pane_1">
                    <div class="cms-form-grid">
                        <div class="cms-field">
                            <label class="cms-label">Step 1 Stage Title</label>
                            <input type="text" name="step1_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('step1_title', 'Submit')); ?>">
                        </div>
                        <div class="cms-field">
                            <label class="cms-label">Step 1 Description</label>
                            <textarea name="step1_desc" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('step1_desc', '')); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 2 Pane -->
                <div class="wf-step-pane" id="wf_pane_2">
                    <div class="cms-form-grid">
                        <div class="cms-field">
                            <label class="cms-label">Step 2 Stage Title</label>
                            <input type="text" name="step2_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('step2_title', 'Review')); ?>">
                        </div>
                        <div class="cms-field">
                            <label class="cms-label">Step 2 Description</label>
                            <textarea name="step2_desc" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('step2_desc', '')); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 3 Pane -->
                <div class="wf-step-pane" id="wf_pane_3">
                    <div class="cms-form-grid">
                        <div class="cms-field">
                            <label class="cms-label">Step 3 Stage Title</label>
                            <input type="text" name="step3_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('step3_title', 'Approval')); ?>">
                        </div>
                        <div class="cms-field">
                            <label class="cms-label">Step 3 Description</label>
                            <textarea name="step3_desc" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('step3_desc', '')); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Step 4 Pane -->
                <div class="wf-step-pane" id="wf_pane_4">
                    <div class="cms-form-grid">
                        <div class="cms-field">
                            <label class="cms-label">Step 4 Stage Title</label>
                            <input type="text" name="step4_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('step4_title', 'Release')); ?>">
                        </div>
                        <div class="cms-field">
                            <label class="cms-label">Step 4 Description</label>
                            <textarea name="step4_desc" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('step4_desc', '')); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Document Pre-Verification, Notifications & CTA -->
        <div class="cms-accordion-card" id="acc_cta">
            <div class="cms-accordion-header" onclick="toggleAcc('acc_cta')">
                <div class="cms-acc-title-wrap">
                    <div class="cms-acc-icon"><i class="fas fa-file-upload"></i></div>
                    <div class="cms-acc-text">
                        <h3>Pre-Verification Banner &amp; Registration Call-to-Action</h3>
                        <p>Upload instructions and bottom homepage registration invite</p>
                    </div>
                </div>
                <div class="cms-acc-chevron"><i class="fas fa-chevron-down"></i></div>
            </div>

            <div class="cms-accordion-body">
                <div class="cms-form-grid">
                    <div class="cms-field">
                        <label class="cms-label">Upload Banner Security Badge</label>
                        <input type="text" name="doc_upload_badge" class="cms-input" value="<?php echo htmlspecialchars(cms_get('doc_upload_badge', 'Secure Upload • 256-bit encryption')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Upload Banner Heading</label>
                        <input type="text" name="doc_upload_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('doc_upload_title', 'Upload Documents Before Your Visit')); ?>">
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Upload Banner Description</label>
                        <textarea name="doc_upload_desc" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('doc_upload_desc', '')); ?></textarea>
                    </div>

                    <div class="cms-field">
                        <label class="cms-label">Bottom CTA Headline</label>
                        <input type="text" name="cta_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('cta_title', 'Ready to Get Started?')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Bottom CTA Description</label>
                        <input type="text" name="cta_desc" class="cms-input" value="<?php echo htmlspecialchars(cms_get('cta_desc', '')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">CTA Primary Button Label &amp; URL</label>
                        <input type="text" name="cta_btn1_text" class="cms-input" value="<?php echo htmlspecialchars(cms_get('cta_btn1_text', 'Register as Resident')); ?>" style="margin-bottom:6px;">
                        <input type="text" name="cta_btn1_link" class="cms-input" value="<?php echo htmlspecialchars(cms_get('cta_btn1_link', 'register.php')); ?>">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">CTA Secondary Button Label &amp; URL</label>
                        <input type="text" name="cta_btn2_text" class="cms-input" value="<?php echo htmlspecialchars(cms_get('cta_btn2_text', 'Sign In')); ?>" style="margin-bottom:6px;">
                        <input type="text" name="cta_btn2_link" class="cms-input" value="<?php echo htmlspecialchars(cms_get('cta_btn2_link', 'login.php')); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Floating Save Bar -->
        <div class="cms-sticky-save-bar">
            <div class="cms-sticky-save-left">
                <i class="fas fa-info-circle" style="color:var(--cms-maroon);"></i>
                <span>Changes will immediately update the live website upon saving.</span>
            </div>
            <div style="display:flex; gap:12px;">
                <button type="submit" class="cms-btn-primary">
                    <i class="fas fa-save"></i> Save All Landing Changes
                </button>
            </div>
        </div>
    </form>

<!-- ======================================================================= -->
<!-- TAB 2: CORE SERVICES & CITIZEN'S CHARTER PROGRAMS (SERVICES.PHP)        -->
<!-- ======================================================================= -->
<?php elseif ($tab === 'services'): ?>

    <form method="POST" action="?tab=services" enctype="multipart/form-data" id="programsForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="cms_action" value="save_programs">

        <!-- Main Visual Program Showcase Card -->
        <div class="cms-accordion-card active">
            <div style="padding: 20px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--cms-border); flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-hand-holding-heart" style="color:var(--cms-maroon); margin-right:8px;"></i> Services Portal &amp; Core Programs Showcase (services.php)</h3>
                    <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Replace pictures and edit program information across all main services cards shown on the public portal</p>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <a href="../services.php" target="_blank" class="cms-btn-secondary">
                        <i class="fas fa-external-link-alt"></i> Preview services.php
                    </a>
                </div>
            </div>

            <!-- Program Selector Switcher Tabs -->
            <div style="padding: 16px 24px 0; background:rgba(0,0,0,0.015); border-bottom:1.5px solid var(--cms-border);">
                <div class="workflow-step-tabs prog-switch-tabs" style="margin-bottom:16px;">
                    <button type="button" class="workflow-step-tab active" onclick="switchProgPane('housing', this)">
                        <i class="fas fa-home"></i> 1. Housing Programs
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchProgPane('aics', this)">
                        <i class="fas fa-hand-holding-heart"></i> 2. AICS Assistance
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchProgPane('senior', this)">
                        <i class="fas fa-user-clock"></i> 3. Senior Citizen
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchProgPane('pwd', this)">
                        <i class="fas fa-wheelchair"></i> 4. PWD Services
                    </button>
                    <button type="button" class="workflow-step-tab" onclick="switchProgPane('appointments', this)">
                        <i class="fas fa-calendar-check"></i> 5. Appointments
                    </button>
                </div>
            </div>

            <div style="padding: 24px;">

<?php
$prog_configs = [
    'housing' => [
        'name' => 'Housing Programs',
        'icon' => 'fas fa-home',
        'default_slides' => ['images/san-lorenzo.png', 'images/housing-sanlorenzo1.png', 'images/housing-sanlorenzo2.png'],
        'default_title' => 'Housing Programs',
        'default_badge' => 'Relocation & In-Site (Own Lot)',
        'default_caption' => 'Affordable & In-Site Housing for Palayan Families',
        'default_desc' => 'Apply for socialized housing opportunities in Palayan City. Includes designated resettlement communities (Akap Village and San Lorenzo NHA) and on-site building grants for lot owners.',
        'subservices_label' => 'Housing Pathways / Sub-Programs (Comma-Separated)',
        'default_subservices' => 'Akap Village, San Lorenzo NHA, In-Site Housing (Own Lot)',
        'default_btn1' => 'View Requirements',
        'default_btn2' => 'Apply Housing',
        'default_btn2_link' => 'apply-housing.php',
    ],
    'aics' => [
        'name' => 'AICS Assistance',
        'icon' => 'fas fa-hand-holding-heart',
        'default_slides' => ['images/Aics1.png', 'images/aics2.png', 'images/aics3.png'],
        'default_title' => 'AICS Assistance',
        'default_badge' => '6 Types of Crisis Relief',
        'default_caption' => 'Comprehensive Assistance in Times of Crisis',
        'default_desc' => 'Assistance to Individuals in Crisis Situation provides urgent financial relief, medical subsidies, burial support, education aid, transportation fare, and food packs for distressed residents.',
        'subservices_label' => 'Crisis Relief Programs (Comma-Separated)',
        'default_subservices' => 'Medical, Funeral / Burial, Educational, Transportation, Food Aid, Cash Relief',
        'default_btn1' => 'View Checklists',
        'default_btn2' => 'Apply AICS',
        'default_btn2_link' => 'aics.php',
    ],
    'senior' => [
        'name' => 'Senior Citizen Services',
        'icon' => 'fas fa-user-clock',
        'default_slides' => ['images/senior2.png', 'images/senior1.png', 'images/senior4.png'],
        'default_title' => 'Senior Citizen Services',
        'default_badge' => 'Window 3 Express Priority Lane',
        'default_caption' => 'Honoring & Empowering Palayan Senior Citizens',
        'default_desc' => 'Dedicated programs for seniors aged 60+. Apply for your official Senior Citizen ID, Medicine & Grocery Purchase Booklets, social pension stipends, and welfare benefits.',
        'subservices_label' => 'Senior Citizen Programs (Comma-Separated)',
        'default_subservices' => 'OSCA ID & Purchase Booklets (Window 3), Social Pension Assistance',
        'default_btn1' => "Citizen's Charter",
        'default_btn2' => 'Senior Portal',
        'default_btn2_link' => 'senior.php',
    ],
    'pwd' => [
        'name' => 'PWD Services',
        'icon' => 'fas fa-wheelchair',
        'default_slides' => ['images/PWD1.png', 'images/PWD2.png', 'images/PWD3.png'],
        'default_title' => 'PWD Services',
        'default_badge' => 'Window 1 Accessible Lane',
        'default_caption' => 'Inclusive Support for Persons with Disabilities',
        'default_desc' => 'Registration and issuance of PWD Identification Cards and Purchase Booklets granting 20% discounts and VAT exemption, alongside free mobility assistive devices.',
        'subservices_label' => 'PWD Programs & Aids (Comma-Separated)',
        'default_subservices' => 'PWD ID & Purchase Booklets (Window 1), Assistive Devices & Mobility Aids',
        'default_btn1' => "Citizen's Charter",
        'default_btn2' => 'PWD Portal',
        'default_btn2_link' => 'pwd.php',
    ],
    'appointments' => [
        'name' => 'Appointment Services',
        'icon' => 'fas fa-calendar-check',
        'default_slides' => ['images/ap1.png', 'images/ap2.png', 'images/ap3.png'],
        'default_title' => 'Appointment Services',
        'default_badge' => 'Zero Physical Waiting Line',
        'default_caption' => 'Schedule Your Consultation Online',
        'default_desc' => 'Schedule social welfare consultations and document submissions online. Choose your preferred date and time, receive instant digital confirmations, and enjoy priority service.',
        'subservices_label' => 'Appointment Options (Comma-Separated)',
        'default_subservices' => 'Express Priority Booking, SMS & Email Reminders',
        'default_btn1' => 'Booking Guide',
        'default_btn2' => 'Book Online',
        'default_btn2_link' => 'appointments.php',
    ],
];

foreach ($prog_configs as $pKey => $cfg): 
    $isActivePane = ($pKey === 'housing');
    $pSlides = cms_get_slides($pKey, $cfg['default_slides']);
?>
                <!-- ==================== PANE: <?php echo strtoupper($pKey); ?> ==================== -->
                <div class="prog-editor-pane <?php echo $isActivePane ? 'active' : ''; ?>" id="pane_<?php echo $pKey; ?>">
                    <div class="cms-prog-editor-layout">
                        <!-- Live Card Preview with Real-time Interactive Slideshow -->
                        <div class="cms-prog-preview-wrap">
                            <div class="cms-live-sync-indicator" style="position:static; margin-bottom:10px; display:inline-flex;">
                                <span class="dot"></span> <span>Live Card Preview (services.php)</span>
                            </div>
                            <div class="cms-prog-preview-card">
                                <div class="cms-prog-pv-banner" id="pv_banner_<?php echo $pKey; ?>">
                                    <div class="cms-prog-pv-slideshow" id="pv_slideshow_<?php echo $pKey; ?>">
                                        <?php foreach ($pSlides as $sIdx => $sImg): ?>
                                            <div class="cms-prog-pv-slide <?php echo $sIdx === 0 ? 'active' : ''; ?>" style="background-image: url('<?php echo cms_asset($sImg); ?>');"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="cms-prog-pv-overlay"></div>
                                    <div class="cms-prog-pv-caption" id="pv_caption_<?php echo $pKey; ?>">
                                        <i class="<?php echo $cfg['icon']; ?>"></i> <?php echo htmlspecialchars(cms_get("prog_{$pKey}_caption", $cfg['default_caption'])); ?>
                                    </div>
                                    <div class="cms-prog-pv-dots" id="pv_dots_<?php echo $pKey; ?>">
                                        <?php for ($d = 0; $d < count($pSlides); $d++): ?>
                                            <span class="pv-dot <?php echo $d === 0 ? 'active' : ''; ?>" onclick="switchPvSlide('<?php echo $pKey; ?>', <?php echo $d; ?>)"></span>
                                        <?php endfor; ?>
                                    </div>
                                    <button type="button" class="cms-prog-pv-arrow prev" onclick="stepPvSlide('<?php echo $pKey; ?>', -1)" title="Previous slide"><i class="fas fa-chevron-left"></i></button>
                                    <button type="button" class="cms-prog-pv-arrow next" onclick="stepPvSlide('<?php echo $pKey; ?>', 1)" title="Next slide"><i class="fas fa-chevron-right"></i></button>
                                </div>
                                <div class="cms-prog-pv-content">
                                    <div class="cms-prog-pv-header">
                                        <div class="cms-prog-pv-icon"><i class="<?php echo $cfg['icon']; ?>"></i></div>
                                        <div>
                                            <h4 class="cms-prog-pv-title" id="pv_title_<?php echo $pKey; ?>"><?php echo htmlspecialchars(cms_get("prog_{$pKey}_title", $cfg['default_title'])); ?></h4>
                                            <span class="cms-prog-pv-badge" id="pv_badge_<?php echo $pKey; ?>"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(cms_get("prog_{$pKey}_badge", $cfg['default_badge'])); ?></span>
                                        </div>
                                    </div>
                                    <p class="cms-prog-pv-desc" id="pv_desc_<?php echo $pKey; ?>"><?php echo htmlspecialchars(cms_get("prog_{$pKey}_desc", $cfg['default_desc'])); ?></p>
                                    <div class="cms-prog-pv-pills" id="pv_pills_<?php echo $pKey; ?>">
                                        <?php 
                                        $pills = explode(',', cms_get("prog_{$pKey}_subservices", $cfg['default_subservices']));
                                        foreach ($pills as $hp): if(trim($hp)): ?>
                                            <span class="cms-prog-pv-pill"><?php echo htmlspecialchars(trim($hp)); ?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                    <div class="cms-prog-pv-buttons">
                                        <div class="cms-prog-pv-btn1" id="pv_btn1_<?php echo $pKey; ?>"><?php echo htmlspecialchars(cms_get("prog_{$pKey}_btn1_text", $cfg['default_btn1'])); ?></div>
                                        <div class="cms-prog-pv-btn2" id="pv_btn2_<?php echo $pKey; ?>"><?php echo htmlspecialchars(cms_get("prog_{$pKey}_btn2_text", $cfg['default_btn2'])); ?> &rarr;</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Controls -->
                        <div>
                            <div class="cms-form-grid">
                                <!-- MULTIPLE PICTURES SLIDESHOW MANAGER -->
                                <div class="cms-field cms-form-full">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                                        <label class="cms-label" style="margin-bottom:0;">
                                            <i class="fas fa-images" style="color:var(--cms-maroon); margin-right:6px;"></i>
                                            <?php echo htmlspecialchars($cfg['name']); ?> Slideshow Pictures 
                                            <span class="cms-label-tag" id="badge_count_<?php echo $pKey; ?>"><?php echo count($pSlides); ?> Slides in Carousel</span>
                                        </label>
                                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                            <!-- Batch Upload Button -->
                                            <label class="cms-btn-primary" style="font-size:0.75rem; padding:6px 14px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                                                <i class="fas fa-cloud-upload-alt"></i> Upload Multiple Pictures
                                                <input type="file" name="prog_<?php echo $pKey; ?>_slide_files[]" multiple accept="image/*" style="display:none;" onchange="handleProgMultipleUpload(this, '<?php echo $pKey; ?>')">
                                            </label>
                                            <!-- Add Manual Slide Button -->
                                            <button type="button" class="cms-btn-secondary" style="font-size:0.75rem; padding:6px 12px;" onclick="addSlideRow('<?php echo $pKey; ?>')">
                                                <i class="fas fa-plus"></i> Add Slide
                                            </button>
                                        </div>
                                    </div>
                                    <p class="cms-help" style="margin:0 0 12px;">
                                        Upload multiple photos to display in this card's slideshow on <strong>services.php</strong>. Slide #1 serves as the primary cover. You can reorder, replace, or delete slides.
                                    </p>

                                    <!-- Slides List -->
                                    <div class="cms-slides-list" id="slides_list_<?php echo $pKey; ?>" style="display:flex; flex-direction:column; gap:10px;">
                                        <?php foreach ($pSlides as $sIdx => $sImg): ?>
                                            <div class="cms-slide-row" data-prog="<?php echo $pKey; ?>" style="display:flex; align-items:center; gap:12px; padding:10px 14px; background:rgba(0,0,0,0.02); border-radius:12px; border:1.5px solid var(--cms-border);">
                                                <div class="cms-slide-badge" style="font-size:0.75rem; font-weight:700; color:var(--cms-maroon); background:rgba(127,3,3,0.08); padding:5px 10px; border-radius:20px; white-space:nowrap; min-width:85px; text-align:center;">
                                                    Slide #<?php echo ($sIdx + 1); ?> <?php echo $sIdx === 0 ? '<span style="color:#059669;">(Cover)</span>' : ''; ?>
                                                </div>
                                                <img src="<?php echo cms_asset($sImg); ?>" class="cms-img-thumb-preview slide-thumb" style="width:72px; height:48px; object-fit:cover; border-radius:6px; flex-shrink:0; border:1px solid rgba(0,0,0,0.08);" alt="Slide <?php echo ($sIdx + 1); ?>" onerror="this.src='../images/logo.png'">
                                                <div style="flex:1; min-width:160px;">
                                                    <input type="text" name="prog_<?php echo $pKey; ?>_slides[]" class="cms-input slide-path-inp" value="<?php echo htmlspecialchars($sImg); ?>" oninput="syncProgSlides('<?php echo $pKey; ?>')" placeholder="images/photo.png or uploads/cms/...">
                                                </div>
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <label class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px; cursor:pointer;" title="Replace this picture">
                                                        <i class="fas fa-sync-alt"></i> Replace
                                                        <input type="file" name="prog_<?php echo $pKey; ?>_slide_replace[<?php echo $sIdx; ?>]" accept="image/*" style="display:none;" onchange="handleSingleSlideReplace(this, '<?php echo $pKey; ?>', this.closest('.cms-slide-row'))">
                                                    </label>
                                                    <button type="button" class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px;" onclick="moveSlide('<?php echo $pKey; ?>', this, -1)" title="Move slide up">
                                                        <i class="fas fa-arrow-up"></i>
                                                    </button>
                                                    <button type="button" class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px;" onclick="moveSlide('<?php echo $pKey; ?>', this, 1)" title="Move slide down">
                                                        <i class="fas fa-arrow-down"></i>
                                                    </button>
                                                    <button type="button" class="cms-btn-danger" style="font-size:0.72rem; padding:6px 10px; color:#ef4444; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:6px; cursor:pointer;" onclick="removeSlide('<?php echo $pKey; ?>', this)" title="Delete slide">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- OVERLAY CAPTION -->
                                <div class="cms-field cms-form-full">
                                    <label class="cms-label">Banner Image Overlay Caption</label>
                                    <input type="text" name="prog_<?php echo $pKey; ?>_caption" id="inp_prog_<?php echo $pKey; ?>_caption" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_caption", $cfg['default_caption'])); ?>" oninput="syncProgPreview('<?php echo $pKey; ?>')">
                                </div>

                                <!-- TITLE & BADGE -->
                                <div class="cms-field">
                                    <label class="cms-label">Program Title</label>
                                    <input type="text" name="prog_<?php echo $pKey; ?>_title" id="inp_prog_<?php echo $pKey; ?>_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_title", $cfg['default_title'])); ?>" oninput="syncProgPreview('<?php echo $pKey; ?>')" required>
                                </div>
                                <div class="cms-field">
                                    <label class="cms-label">Badge Tag Pill</label>
                                    <input type="text" name="prog_<?php echo $pKey; ?>_badge" id="inp_prog_<?php echo $pKey; ?>_badge" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_badge", $cfg['default_badge'])); ?>" oninput="syncProgPreview('<?php echo $pKey; ?>')">
                                </div>

                                <!-- DESCRIPTION -->
                                <div class="cms-field cms-form-full">
                                    <label class="cms-label">Program Overview / Description</label>
                                    <textarea name="prog_<?php echo $pKey; ?>_desc" id="inp_prog_<?php echo $pKey; ?>_desc" class="cms-textarea" rows="3" oninput="syncProgPreview('<?php echo $pKey; ?>')"><?php echo htmlspecialchars(cms_get("prog_{$pKey}_desc", $cfg['default_desc'])); ?></textarea>
                                </div>

                                <!-- SUBSERVICES -->
                                <div class="cms-field cms-form-full">
                                    <label class="cms-label"><?php echo htmlspecialchars($cfg['subservices_label']); ?></label>
                                    <input type="text" name="prog_<?php echo $pKey; ?>_subservices" id="inp_prog_<?php echo $pKey; ?>_subservices" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_subservices", $cfg['default_subservices'])); ?>" oninput="syncProgPills('<?php echo $pKey; ?>')">
                                </div>

                                <!-- BUTTON 1 & 2 -->
                                <div class="cms-field">
                                    <label class="cms-label">Button 1 Label</label>
                                    <input type="text" name="prog_<?php echo $pKey; ?>_btn1_text" id="inp_prog_<?php echo $pKey; ?>_btn1_text" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_btn1_text", $cfg['default_btn1'])); ?>" oninput="syncProgPreview('<?php echo $pKey; ?>')">
                                </div>
                                <div class="cms-field">
                                    <label class="cms-label">Button 2 Label &amp; URL Destination</label>
                                    <input type="text" name="prog_<?php echo $pKey; ?>_btn2_text" id="inp_prog_<?php echo $pKey; ?>_btn2_text" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_btn2_text", $cfg['default_btn2'])); ?>" style="margin-bottom:6px;" oninput="syncProgPreview('<?php echo $pKey; ?>')">
                                    <input type="text" name="prog_<?php echo $pKey; ?>_btn2_link" class="cms-input" value="<?php echo htmlspecialchars(cms_get("prog_{$pKey}_btn2_link", $cfg['default_btn2_link'])); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>

            </div>

            <!-- Sticky Save Bar for Program Changes -->
            <div class="cms-sticky-save-bar">
                <div class="cms-sticky-save-left">
                    <i class="fas fa-info-circle" style="color:var(--cms-maroon);"></i>
                    <span>All program pictures, captions, and details immediately update across <strong>services.php</strong> upon saving.</span>
                </div>
                <div>
                    <button type="submit" class="cms-btn-primary">
                        <i class="fas fa-save"></i> Save All Program Changes
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Optional Collapsed Drawer: Database Services Catalog Table -->
    <div class="cms-accordion-card collapsed" id="acc_raw_services" style="margin-top:20px;">
        <div class="cms-accordion-header" onclick="toggleAcc('acc_raw_services')">
            <div class="cms-acc-title-wrap">
                <div class="cms-acc-icon"><i class="fas fa-database"></i></div>
                <div class="cms-acc-text">
                    <h3>Detailed Database Services Records (<?php echo count($all_services); ?> items)</h3>
                    <p>Advanced: Manage internal database codes and granular sub-service records</p>
                </div>
            </div>
            <div class="cms-acc-chevron"><i class="fas fa-chevron-down"></i></div>
        </div>

        <div class="cms-accordion-body">
            <div style="padding:16px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--cms-border);">
                <span class="cms-counter-chip"><i class="fas fa-database"></i> Database Records</span>
                <button type="button" class="cms-btn-primary" onclick="openServiceModal()" style="font-size:0.8rem !important; padding:8px 18px !important;">
                    <i class="fas fa-plus"></i> Add New Service Record
                </button>
            </div>

            <div class="cms-toolbar">
                <div class="cms-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="serviceSearchInput" class="cms-search-input" placeholder="Search service records..." onkeyup="filterServicesTable()">
                </div>
            </div>

            <div class="cms-table-wrap">
                <table class="cms-modern-table" id="servicesModernTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th>Service Information</th>
                            <th>Category</th>
                            <th>Processing Time</th>
                            <th>Status</th>
                            <th style="text-align: right; width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="servicesTableBody">
                        <?php foreach ($all_services as $svc): 
                            $cat_lower = strtolower($svc['category'] ?? '');
                            $cat_class = 'cms-cat-general';
                            if (strpos($cat_lower, 'aics') !== false) $cat_class = 'cms-cat-aics';
                            elseif (strpos($cat_lower, 'housing') !== false) $cat_class = 'cms-cat-housing';
                            elseif (strpos($cat_lower, 'senior') !== false) $cat_class = 'cms-cat-senior';
                            elseif (strpos($cat_lower, 'pwd') !== false) $cat_class = 'cms-cat-pwd';
                        ?>
                        <tr class="service-row" data-name="<?php echo htmlspecialchars(strtolower($svc['service_name'] . ' ' . ($svc['service_code'] ?? ''))); ?>" data-cat="<?php echo htmlspecialchars($cat_lower ?: 'general'); ?>">
                            <td style="font-weight: 700; color: var(--text-muted); text-align: center;">
                                #<?php echo $svc['sort_order']; ?>
                            </td>
                            <td>
                                <div class="cms-service-cell">
                                    <div class="cms-service-avatar">
                                        <i class="<?php echo htmlspecialchars($svc['icon'] ?: 'fas fa-hands-helping'); ?>"></i>
                                    </div>
                                    <div>
                                        <div class="cms-service-info-title"><?php echo htmlspecialchars($svc['service_name']); ?></div>
                                        <div class="cms-service-info-code"><code><?php echo htmlspecialchars($svc['service_code'] ?: 'SVC-' . $svc['service_id']); ?></code></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="cms-cat-badge <?php echo $cat_class; ?>">
                                    <?php echo htmlspecialchars($svc['category'] ?: 'General'); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-secondary); white-space: nowrap;">
                                <?php echo htmlspecialchars($svc['processing_time'] ?: 'Standard'); ?>
                            </td>
                            <td>
                                <form method="POST" action="?tab=services" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="cms_action" value="toggle_service">
                                    <input type="hidden" name="service_id" value="<?php echo $svc['service_id']; ?>">
                                    <button type="submit" class="cms-status-pill <?php echo $svc['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="status-dot"></span>
                                        <span><?php echo $svc['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div class="cms-actions-group">
                                    <button type="button" class="cms-btn-action edit" onclick='editService(<?php echo json_encode($svc, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Service">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Service Add/Edit Modal (Preserved for backward compatibility) -->
    <div class="cms-modal" id="serviceModal">
        <div class="cms-modal-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid var(--cms-maroon);">
                <h3 id="serviceModalTitle" style="margin:0; font-size:1.15rem; color:var(--text-primary);"><i class="fas fa-plus-circle" style="color:var(--cms-maroon);"></i> Add New Service</h3>
                <button type="button" onclick="closeServiceModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="?tab=services">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="cms_action" value="save_service">
                <input type="hidden" name="service_id" id="svc_id" value="0">

                <div class="cms-form-grid">
                    <div class="cms-field">
                        <label class="cms-label">Service Title / Name *</label>
                        <input type="text" name="service_name" id="svc_name" class="cms-input" required>
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Service Code</label>
                        <input type="text" name="service_code" id="svc_code" class="cms-input" placeholder="e.g. AICS_MED, HOUSING_AKAP">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Category</label>
                        <select name="category" id="svc_cat" class="cms-select">
                            <option value="AICS">Crisis Assistance (AICS)</option>
                            <option value="Housing Programs">Housing Programs</option>
                            <option value="Senior Citizen">Senior Citizen Affairs</option>
                            <option value="PWD Services">Persons with Disability (PWD)</option>
                            <option value="Appointments">Appointments &amp; Scheduling</option>
                            <option value="Documents">Document Pre-Verification</option>
                            <option value="General">General Social Services</option>
                        </select>
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Badge Tag (e.g. Relocation Housing, Financial Aid)</label>
                        <input type="text" name="badge" id="svc_badge" class="cms-input">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">FontAwesome Icon Class</label>
                        <input type="text" name="icon" id="svc_icon" class="cms-input" placeholder="fas fa-hands-helping">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Sort Order (Lower numbers appear first)</label>
                        <input type="number" name="sort_order" id="svc_sort" class="cms-input" value="0">
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Short Summary Description</label>
                        <textarea name="short_desc" id="svc_sdesc" class="cms-textarea" rows="2" placeholder="Brief 1-2 sentence overview for cards and catalogs"></textarea>
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Full Overview &amp; Purpose</label>
                        <textarea name="description" id="svc_desc" class="cms-textarea" rows="3"></textarea>
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Who Can Apply (Eligibility Criteria)</label>
                        <textarea name="eligibility" id="svc_elig" class="cms-textarea" rows="3" placeholder="List criteria, 1 per line"></textarea>
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Documentary Requirements Checklist</label>
                        <textarea name="requirements" id="svc_req" class="cms-textarea" rows="3" placeholder="List requirements, 1 per line"></textarea>
                    </div>
                    <div class="cms-field cms-form-full">
                        <label class="cms-label">Citizen's Charter Application Procedure</label>
                        <textarea name="procedure" id="svc_proc" class="cms-textarea" rows="3" placeholder="Step 1: ...&#10;Step 2: ..."></textarea>
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Processing Time</label>
                        <input type="text" name="processing_time" id="svc_proc_time" class="cms-input" value="1-3 working days">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Fees</label>
                        <input type="text" name="fees" id="svc_fees" class="cms-input" value="Free of Charge">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Where to Apply / Office Desk</label>
                        <input type="text" name="where_to_apply" id="svc_where_to" class="cms-input" placeholder="e.g. CSWDO 2nd Floor, Palayan City Hall">
                    </div>
                    <div class="cms-field">
                        <label class="cms-label">Direct Application URL / Link</label>
                        <input type="text" name="apply_url" id="svc_apply_url" class="cms-input" placeholder="e.g. aics.php, apply-housing.php">
                    </div>

                    <div class="cms-field cms-form-full" style="display:flex; gap:24px; align-items:center; margin-top:8px;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; font-size:0.88rem;">
                            <input type="checkbox" name="is_active" id="svc_active" value="1" checked>
                            <span>Active (Published on website)</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; font-size:0.88rem;">
                            <input type="checkbox" name="is_featured" id="svc_featured" value="1" checked>
                            <span>Featured on Homepage</span>
                        </label>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; padding-top:14px; border-top:1px solid var(--cms-border);">
                    <button type="button" class="cms-btn-secondary" onclick="closeServiceModal()">Cancel</button>
                    <button type="submit" class="cms-btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>

<!-- ======================================================================= -->
<!-- TAB 3: REQUIREMENTS & CITIZEN'S CHARTER                                 -->
<!-- ======================================================================= -->
<?php elseif ($tab === 'requirements'): ?>

    <!-- AICS Requirements Section -->
    <div class="cms-accordion-card active">
        <div style="padding: 20px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--cms-border); flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-tasks" style="color:var(--cms-maroon); margin-right:8px;"></i> AICS Documentary Requirements Checklist</h3>
                <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Manage mandatory and conditional documents per assistance type</p>
            </div>
            <button type="button" class="cms-btn-primary" onclick="openReqModal()">
                <i class="fas fa-plus"></i> Add Document Requirement
            </button>
        </div>

        <!-- Filter Pill Bar & Search -->
        <div class="cms-pill-filter-bar">
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-right:4px;">Filter by Assistance:</span>
            <button type="button" class="cms-filter-pill-btn active" onclick="filterAicsReqs('all', this)">All (<?php echo count($aics_reqs); ?>)</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('medical', this)">Medical</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('funeral', this)">Funeral / Burial</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('educational', this)">Educational</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('transportation', this)">Transportation</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('food', this)">Food</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('cash_relief', this)">Cash Relief</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterAicsReqs('material', this)">Material</button>
        </div>

        <div class="cms-toolbar">
            <div class="cms-search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="reqSearchInput" class="cms-search-input" placeholder="Search document name, code, or issuing office..." onkeyup="filterReqsSearch()">
            </div>
            <div style="font-size:0.82rem; color:var(--text-muted);">
                Showing documents required for citizen application verification
            </div>
        </div>

        <div class="cms-table-wrap">
            <table class="cms-modern-table" id="reqsModernTable">
                <thead>
                    <tr>
                        <th>Assistance Type</th>
                        <th>Document Requirement</th>
                        <th>System Code</th>
                        <th>Submission Rule</th>
                        <th>Description / Issuing Authority</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="reqsTableBody">
                    <?php foreach ($aics_reqs as $rq): ?>
                    <tr class="req-row" data-type="<?php echo htmlspecialchars(strtolower($rq['request_type'])); ?>" data-search="<?php echo htmlspecialchars(strtolower($rq['document_name'] . ' ' . $rq['document_code'] . ' ' . ($rq['description'] ?? ''))); ?>">
                        <td>
                            <span class="cms-cat-badge cms-cat-aics" style="text-transform: capitalize;">
                                <i class="fas fa-hand-holding-medical"></i> <?php echo htmlspecialchars(str_replace('_', ' ', $rq['request_type'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <i class="far fa-file-alt" style="color:var(--cms-maroon); font-size:1rem;"></i>
                                <span style="font-weight: 700; color: var(--text-primary); font-size:0.9rem;"><?php echo htmlspecialchars($rq['document_name']); ?></span>
                            </div>
                        </td>
                        <td>
                            <code style="background:rgba(0,0,0,0.04); padding:3px 7px; border-radius:4px; font-size:0.75rem; border:1px solid var(--cms-border);"><?php echo htmlspecialchars($rq['document_code']); ?></code>
                        </td>
                        <td>
                            <?php if ($rq['is_required']): ?>
                                <span style="background: #FEE2E2; color: #991B1B; padding: 4px 10px; border-radius: 50px; font-weight: 700; font-size: 0.72rem; display:inline-flex; align-items:center; gap:5px; border:1px solid #FECACA;">
                                    <i class="fas fa-exclamation-circle"></i> Mandatory
                                </span>
                            <?php else: ?>
                                <span style="background: #E0F2FE; color: #075985; padding: 4px 10px; border-radius: 50px; font-weight: 700; font-size: 0.72rem; display:inline-flex; align-items:center; gap:5px; border:1px solid #BAE6FD;">
                                    <i class="fas fa-info-circle"></i> Conditional
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.82rem; color: var(--text-secondary); max-width: 280px;">
                            <i class="fas fa-building" style="color:var(--text-muted); margin-right:4px; font-size:0.75rem;"></i>
                            <?php echo htmlspecialchars($rq['description'] ?: 'Official issuing office'); ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <div class="cms-actions-group">
                                <button type="button" class="cms-btn-action edit" onclick='editReq(<?php echo json_encode($rq, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="Edit Requirement">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" action="?tab=requirements" style="display:inline;" onsubmit="return confirm('Delete this requirement checklist item?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="cms_action" value="delete_aics_requirement">
                                    <input type="hidden" name="req_id" value="<?php echo $rq['req_id']; ?>">
                                    <button type="submit" class="cms-btn-action delete" title="Delete Requirement">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr id="noReqsFoundRow" style="display:none;">
                        <td colspan="6" style="text-align:center; padding:36px 20px; color:var(--text-muted);">
                            <i class="fas fa-search" style="font-size:1.8rem; margin-bottom:8px; display:block; opacity:0.4;"></i>
                            No document requirements match this filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Housing Programs Requirements Section -->
    <div class="cms-accordion-card active">
        <div style="padding: 20px 24px; border-bottom:1.5px solid var(--cms-border);">
            <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-city" style="color:var(--cms-maroon); margin-right:8px;"></i> Housing Programs &amp; Eligibility Guidelines</h3>
            <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Update municipal resettlement and in-site housing program requirements</p>
        </div>

        <div style="padding: 24px;" class="cms-form-grid">
            <?php foreach ($housing_programs as $hp): ?>
            <div style="background: rgba(0,0,0,0.02); padding: 18px; border-radius: var(--cms-radius-md); border: 1.5px solid var(--cms-border);">
                <form method="POST" action="?tab=requirements">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="cms_action" value="save_housing_program">
                    <input type="hidden" name="program_id" value="<?php echo $hp['program_id']; ?>">

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h4 style="margin:0; font-size:1rem; color:var(--text-primary); font-weight:700;">
                            <i class="fas fa-home" style="color:var(--cms-maroon);"></i> <?php echo htmlspecialchars($hp['program_name']); ?>
                        </h4>
                        <span class="service-tag"><?php echo htmlspecialchars($hp['housing_type']); ?></span>
                    </div>

                    <div style="margin-bottom:10px;">
                        <label class="cms-label">Program Name</label>
                        <input type="text" name="program_name" class="cms-input" value="<?php echo htmlspecialchars($hp['program_name']); ?>" required>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label class="cms-label">Location / Site</label>
                        <input type="text" name="location" class="cms-input" value="<?php echo htmlspecialchars($hp['location'] ?? ''); ?>">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label class="cms-label">Requirements Checklist</label>
                        <textarea name="requirements" class="cms-textarea" rows="4"><?php echo htmlspecialchars($hp['requirements'] ?? ''); ?></textarea>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                        <select name="status" class="cms-select" style="width: auto;">
                            <option value="active" <?php echo ($hp['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo ($hp['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo ($hp['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        </select>
                        <button type="submit" class="cms-btn-primary" style="padding:8px 18px !important; font-size:0.8rem !important;">
                            <i class="fas fa-save"></i> Save Program
                        </button>
                    </div>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Requirement Modal -->
    <div class="cms-modal" id="reqModal">
        <div class="cms-modal-box" style="max-width: 580px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid var(--cms-maroon);">
                <h3 id="reqModalTitle" style="margin:0; font-size:1.15rem; color:var(--text-primary);">Add AICS Requirement</h3>
                <button type="button" onclick="closeReqModal()" style="border:none; background:none; font-size:1.4rem; cursor:pointer; color:var(--text-muted);">&times;</button>
            </div>

            <form method="POST" action="?tab=requirements">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="cms_action" value="save_aics_requirement">
                <input type="hidden" name="req_id" id="modal_req_id" value="0">

                <div class="cms-field">
                    <label class="cms-label">Assistance Type</label>
                    <select name="request_type" id="modal_req_type" class="cms-select">
                        <option value="medical">Medical Assistance</option>
                        <option value="funeral">Funeral / Burial Assistance</option>
                        <option value="educational">Educational Assistance</option>
                        <option value="transportation">Transportation Assistance</option>
                        <option value="food">Food Assistance</option>
                        <option value="cash_relief">Cash Relief / Emergency</option>
                        <option value="material">Material Assistance</option>
                    </select>
                </div>
                <div class="cms-field">
                    <label class="cms-label">Document Name *</label>
                    <input type="text" name="document_name" id="modal_doc_name" class="cms-input" required placeholder="e.g. Clinical Abstract / Medical Prescription">
                </div>
                <div class="cms-field">
                    <label class="cms-label">Document Code</label>
                    <input type="text" name="document_code" id="modal_doc_code" class="cms-input" placeholder="e.g. clinical_abstract">
                </div>
                <div class="cms-field">
                    <label class="cms-label">Description / Source Office</label>
                    <input type="text" name="description" id="modal_doc_desc" class="cms-input" placeholder="e.g. Issued by attending licensed physician">
                </div>
                <div class="cms-field">
                    <label class="cms-label">Sort Order</label>
                    <input type="number" name="sort_order" id="modal_doc_sort" class="cms-input" value="0">
                </div>
                <div class="cms-field">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:600;">
                        <input type="checkbox" name="is_required" id="modal_doc_req" value="1" checked>
                        <span>Mandatory Document (Required for submission)</span>
                    </label>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; padding-top:14px; border-top:1px solid var(--cms-border);">
                    <button type="button" class="cms-btn-secondary" onclick="closeReqModal()">Cancel</button>
                    <button type="submit" class="cms-btn-primary">Save Requirement</button>
                </div>
            </form>
        </div>
    </div>

<!-- ======================================================================= -->
<!-- TAB 4: SECTION VISIBILITY & TOGGLES                                     -->
<!-- ======================================================================= -->
<?php elseif ($tab === 'visibility'): ?>

    <form method="POST" action="?tab=visibility" id="visibilityForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="cms_action" value="save_visibility">

        <div class="cms-accordion-card active">
            <div style="padding: 20px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--cms-border); flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-eye" style="color:var(--cms-maroon); margin-right:8px;"></i> Public Website Section Visibility Controls</h3>
                    <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Dynamically publish or hide homepage sections with real-time preview toggles</p>
                </div>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="cms-btn-secondary" onclick="setAllSections(true)" style="padding:8px 16px !important; font-size:0.8rem !important;">
                        <i class="fas fa-check-double"></i> Enable All
                    </button>
                    <button type="button" class="cms-btn-secondary" onclick="setAllSections(false)" style="padding:8px 16px !important; font-size:0.8rem !important;">
                        <i class="fas fa-eye-slash"></i> Hide All
                    </button>
                    <button type="submit" class="cms-btn-primary">
                        <i class="fas fa-save"></i> Save Visibility Settings
                    </button>
                </div>
            </div>

            <div style="padding: 24px;">
                <!-- Switch 1: Live CSWDO Stats -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-chart-pie"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Live CSWDO Statistics Section</h4>
                                <span class="cms-vis-placement-tag">Hero Stats Counter</span>
                            </div>
                            <p class="cms-vis-desc">Displays live metrics: total appointments scheduled, AICS requests tracked, housing beneficiaries served, and resident satisfaction percentage.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_hero_stats', true) ? 'visible' : 'hidden'; ?>" id="status_show_hero_stats">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_hero_stats', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_hero_stats" value="1" <?php echo cms_bool('show_hero_stats', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_hero_stats')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Switch 2: Housing Highlight Video -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-video"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Housing Highlight Video Showcase</h4>
                                <span class="cms-vis-placement-tag">Mid-Page Feature Video</span>
                            </div>
                            <p class="cms-vis-desc">Showcases the official video player for Akap Village &amp; San Lorenzo NHA resettlement with comprehensive beneficiary management highlights.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_housing_section', true) ? 'visible' : 'hidden'; ?>" id="status_show_housing_section">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_housing_section', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_housing_section" value="1" <?php echo cms_bool('show_housing_section', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_housing_section')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Switch 3: Transparency 4-Step Process -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-stream"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Transparency &amp; Accountability (4-Step Workflow)</h4>
                                <span class="cms-vis-placement-tag">Citizen Guide Section</span>
                            </div>
                            <p class="cms-vis-desc">Guides citizens step-by-step through Submit &rarr; Review &rarr; Approval &rarr; Release tracking stages with clear operational timelines.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_transparency_section', true) ? 'visible' : 'hidden'; ?>" id="status_show_transparency_section">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_transparency_section', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_transparency_section" value="1" <?php echo cms_bool('show_transparency_section', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_transparency_section')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Switch 4: Multi-Channel Notifications -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-bell"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Multi-Channel Automated Notifications Showcase</h4>
                                <span class="cms-vis-placement-tag">Feature Showcase</span>
                            </div>
                            <p class="cms-vis-desc">Informs citizens of real-time SMS text alerts, email updates, and portal in-app notifications throughout their application cycle.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_notification_section', true) ? 'visible' : 'hidden'; ?>" id="status_show_notification_section">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_notification_section', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_notification_section" value="1" <?php echo cms_bool('show_notification_section', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_notification_section')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Switch 5: Document Pre-Verification Banner -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Document Pre-Verification Banner</h4>
                                <span class="cms-vis-placement-tag">Advance Verification</span>
                            </div>
                            <p class="cms-vis-desc">Prompts residents to upload required certificates and valid IDs prior to in-person City Hall visits to bypass queue delays.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_document_upload_section', true) ? 'visible' : 'hidden'; ?>" id="status_show_document_upload_section">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_document_upload_section', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_document_upload_section" value="1" <?php echo cms_bool('show_document_upload_section', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_document_upload_section')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Switch 6: Featured Services Grid -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-th-large"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Integrated Featured Services Grid</h4>
                                <span class="cms-vis-placement-tag">Homepage Services</span>
                            </div>
                            <p class="cms-vis-desc">Displays the dynamic cards grid for Appointments, Crisis Aid (AICS), and Housing Assistance programs on the homepage.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_services_section', true) ? 'visible' : 'hidden'; ?>" id="status_show_services_section">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_services_section', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_services_section" value="1" <?php echo cms_bool('show_services_section', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_services_section')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Switch 7: Bottom CTA Banner -->
                <div class="cms-vis-card">
                    <div class="cms-vis-left">
                        <div class="cms-vis-icon"><i class="fas fa-user-plus"></i></div>
                        <div>
                            <div class="cms-vis-title-row">
                                <h4 class="cms-vis-title">Registration Call-to-Action (CTA) Banner</h4>
                                <span class="cms-vis-placement-tag">Footer Callout</span>
                            </div>
                            <p class="cms-vis-desc">The high-conversion bottom banner inviting residents to create an account or sign in for immediate access to welfare services.</p>
                        </div>
                    </div>
                    <div class="cms-vis-right">
                        <span class="cms-vis-status-text <?php echo cms_bool('show_cta_section', true) ? 'visible' : 'hidden'; ?>" id="status_show_cta_section">
                            <i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> <?php echo cms_bool('show_cta_section', true) ? 'Live on Homepage' : 'Hidden from Public'; ?>
                        </span>
                        <label class="switch-wrap">
                            <input type="checkbox" name="show_cta_section" value="1" <?php echo cms_bool('show_cta_section', true) ? 'checked' : ''; ?> onchange="toggleSectionStatus(this, 'status_show_cta_section')">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Sticky Save Bar for Visibility -->
            <div style="padding: 16px 24px; border-top:1.5px solid var(--cms-border); display:flex; justify-content:flex-end;">
                <button type="submit" class="cms-btn-primary">
                    <i class="fas fa-save"></i> Save Visibility Settings
                </button>
            </div>
        </div>
    </form>

<!-- ======================================================================= -->
<!-- TAB 5: MEDIA LIBRARY                                                    -->
<!-- ======================================================================= -->
<?php elseif ($tab === 'media'): ?>

    <!-- Media Upload Card -->
    <div class="cms-accordion-card active">
        <div style="padding: 20px 24px; border-bottom:1.5px solid var(--cms-border);">
            <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-cloud-upload-alt" style="color:var(--cms-maroon); margin-right:8px;"></i> Upload Media Asset</h3>
            <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Upload new banners, logos, posters, or photos to the portal library (MP4 videos up to 100MB)</p>
        </div>

        <form method="POST" action="?tab=media" enctype="multipart/form-data" style="padding: 24px;" id="mediaUploadForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="cms_action" value="upload_media">

            <!-- Interactive Visual Dropzone -->
            <div class="cms-upload-dropzone" id="mediaDropzone" onclick="document.getElementById('mediaUploadFileInput').click()">
                <input type="file" name="media_file" id="mediaUploadFileInput" required accept="image/*,video/mp4,video/webm" style="display:none;" onchange="handleMediaFileSelect(this)">
                <div id="dropzoneEmptyState">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <h4 style="margin:0 0 4px; font-size:1rem; color:var(--text-primary); font-weight:700;">Drag and drop file here, or click to browse</h4>
                    <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">Supports PNG, JPG, WEBP, GIF, SVG, and MP4 videos (up to 100MB)</p>
                </div>
                <div id="dropzoneSelectedState" style="display:none; align-items:center; justify-content:center; gap:16px;">
                    <img id="uploadPreviewImg" src="" alt="Preview" style="width:70px; height:70px; border-radius:10px; object-fit:cover; border:1px solid var(--cms-border); background:#000;">
                    <div style="text-align:left;">
                        <div id="uploadFileName" style="font-weight:700; color:var(--text-primary); font-size:0.9rem;"></div>
                        <div id="uploadFileSize" style="font-size:0.75rem; color:var(--text-muted);"></div>
                        <span style="display:inline-block; margin-top:4px; font-size:0.72rem; color:var(--cms-maroon); font-weight:700;"><i class="fas fa-sync-alt"></i> Click to change file</span>
                    </div>
                </div>
            </div>

            <div class="cms-form-grid" style="margin-top:18px;">
                <div class="cms-field">
                    <label class="cms-label">Target Asset Category</label>
                    <select name="media_category" class="cms-select">
                        <option value="banners">Banners &amp; Artwork</option>
                        <option value="logos">Logos &amp; Seals</option>
                        <option value="services">Services Graphics</option>
                        <option value="announcements">Announcements</option>
                        <option value="general">General Media</option>
                    </select>
                </div>
                <div class="cms-field">
                    <label class="cms-label">Alt Text / Descriptive Label</label>
                    <input type="text" name="media_alt" class="cms-input" placeholder="e.g. Palayan City Hall Front View">
                </div>
            </div>

            <div style="margin-top:18px; display:flex; justify-content:flex-end;">
                <button type="submit" class="cms-btn-primary">
                    <i class="fas fa-upload"></i> Upload &amp; Add to Library
                </button>
            </div>
        </form>
    </div>

    <!-- Media Assets Grid Gallery -->
    <div class="cms-accordion-card active">
        <div style="padding: 20px 24px; border-bottom:1.5px solid var(--cms-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-images" style="color:var(--cms-maroon); margin-right:8px;"></i> Media Assets Gallery</h3>
                <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Manage and copy paths for <?php echo count($media_assets); ?> files stored in uploads</p>
            </div>
            <span class="cms-counter-chip"><i class="fas fa-photo-video"></i> <?php echo count($media_assets); ?> Total Files</span>
        </div>

        <!-- Filter Pill Bar for Media -->
        <div class="cms-pill-filter-bar">
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-right:4px;">Category:</span>
            <button type="button" class="cms-filter-pill-btn active" onclick="filterMediaGalleryCategory('all', this)">All (<?php echo count($media_assets); ?>)</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterMediaGalleryCategory('banners', this)">Banners</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterMediaGalleryCategory('logos', this)">Logos</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterMediaGalleryCategory('services', this)">Services</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterMediaGalleryCategory('announcements', this)">Announcements</button>
            <button type="button" class="cms-filter-pill-btn" onclick="filterMediaGalleryCategory('general', this)">General</button>
        </div>

        <!-- Real-Time Search Bar -->
        <div class="cms-toolbar">
            <div class="cms-search-box" style="max-width:420px;">
                <i class="fas fa-search"></i>
                <input type="text" id="mediaSearchInput" class="cms-search-input" placeholder="Search assets by file name or alt text..." onkeyup="filterMediaGallerySearch()">
            </div>
            <div style="font-size:0.82rem; color:var(--text-muted);">
                Click <strong>"Copy Path"</strong> on any asset to use it directly in CMS forms
            </div>
        </div>

        <!-- Media Grid Container (Constrained thumbnail cards) -->
        <div style="padding: 24px;">
            <div class="media-grid" id="mediaAssetsGrid">
                <?php foreach ($media_assets as $med): 
                    $ext = strtoupper(pathinfo($med['file_name'] ?? '', PATHINFO_EXTENSION) ?: 'IMG');
                    $is_video = (strpos($med['file_type'] ?? '', 'video') !== false) || ($ext === 'MP4');
                ?>
                <div class="media-card" data-cat="<?php echo htmlspecialchars(strtolower($med['category'] ?? 'general')); ?>" data-name="<?php echo htmlspecialchars(strtolower(($med['file_name'] ?? '') . ' ' . ($med['alt_text'] ?? ''))); ?>">
                    <div class="media-card-thumb-wrap" style="height:140px; max-height:140px; overflow:hidden;">
                        <span class="media-type-badge"><?php echo htmlspecialchars($ext); ?></span>
                        <span class="media-cat-badge-top"><?php echo htmlspecialchars($med['category'] ?: 'general'); ?></span>
                        
                        <?php if ($is_video): ?>
                            <i class="fas fa-video" style="font-size: 2.2rem; color: #fff;"></i>
                        <?php else: ?>
                            <img src="../<?php echo htmlspecialchars($med['file_path']); ?>" alt="<?php echo htmlspecialchars($med['alt_text'] ?? ''); ?>" loading="lazy" onerror="this.src='../images/logo.png'" style="width:100%; height:140px; object-fit:cover; max-height:140px;">
                        <?php endif; ?>
                    </div>
                    <div class="media-card-body">
                        <div>
                            <div class="media-card-name" title="<?php echo htmlspecialchars($med['file_name']); ?>">
                                <?php echo htmlspecialchars($med['file_name']); ?>
                            </div>
                            <div class="media-card-meta">
                                <span><?php echo round(($med['file_size'] ?? 0) / 1024, 1); ?> KB</span>
                                <?php if (!empty($med['dimensions'])): ?>
                                    <span>&bull; <?php echo htmlspecialchars($med['dimensions']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="media-card-actions">
                            <button type="button" class="media-btn-icon" onclick="copyMediaAssetPath('<?php echo htmlspecialchars($med['file_path']); ?>')" title="Copy path to clipboard">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <a href="../<?php echo htmlspecialchars($med['file_path']); ?>" target="_blank" class="media-btn-icon" title="Open full asset">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <form method="POST" action="?tab=media" style="display:inline;" onsubmit="return confirm('Delete this media asset permanently?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="cms_action" value="delete_media">
                                <input type="hidden" name="media_id" value="<?php echo $med['media_id']; ?>">
                                <button type="submit" class="media-btn-icon btn-danger" style="color:#DC2626;" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="noMediaFoundMsg" style="display:none; text-align:center; padding:48px 20px; color:var(--text-muted);">
                <i class="fas fa-images" style="font-size:2.4rem; opacity:0.3; margin-bottom:12px; display:block;"></i>
                No media assets match your search or category filter.
            </div>
        </div>
    </div>

<!-- ======================================================================= -->
<!-- TAB 6: BRANDING & CONTACT INFO                                          -->
<!-- ======================================================================= -->
<?php elseif ($tab === 'branding'): ?>

    <form method="POST" action="?tab=branding" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="cms_action" value="save_branding">

        <div class="cms-accordion-card active">
            <div style="padding: 20px 24px; display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid var(--cms-border); flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin:0; font-size:1.15rem; font-weight:700; color:var(--text-primary);"><i class="fas fa-sliders-h" style="color:var(--cms-maroon); margin-right:8px;"></i> Website Branding &amp; Portal Identity</h3>
                    <p style="margin:2px 0 0; font-size:0.8rem; color:var(--text-muted);">Change portal logos, office names, contact hotlines, operating hours, and social media links</p>
                </div>
                <button type="submit" class="cms-btn-primary">
                    <i class="fas fa-save"></i> Save Branding
                </button>
            </div>

            <div style="padding: 24px;" class="cms-form-grid">
                <div class="cms-field">
                    <label class="cms-label">Primary Portal Brand Name</label>
                    <input type="text" name="site_title" class="cms-input" value="<?php echo htmlspecialchars(cms_get('site_title', 'ONE PALAYAN-KALINGA HUB')); ?>" required>
                    <span class="cms-help">Displayed in the header bar across all pages.</span>
                </div>

                <div class="cms-field">
                    <label class="cms-label">Office Subtitle / Tagline</label>
                    <input type="text" name="site_tagline" class="cms-input" value="<?php echo htmlspecialchars(cms_get('site_tagline', 'CSWDO Palayan City')); ?>" required>
                </div>

                <div class="cms-field cms-form-full">
                    <label class="cms-label">Official Seal / Logo File</label>
                    <div style="display:flex; align-items:center; gap:16px; padding:14px; background:rgba(0,0,0,0.02); border-radius:10px; border:1px solid var(--cms-border);">
                        <img src="../<?php echo ltrim(cms_get('site_logo', 'images/logo.png'), './'); ?>" alt="Current Logo" class="cms-preview-thumb" style="width:70px; height:70px; border-radius:50%; object-fit:contain; background:#fff;">
                        <div>
                            <strong>Upload Replacement Logo:</strong>
                            <input type="file" name="site_logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="display:block; margin-top:6px; font-size:0.8rem;">
                            <small class="cms-help">Current path: <code><?php echo htmlspecialchars(cms_get('site_logo', 'images/logo.png')); ?></code></small>
                        </div>
                    </div>
                </div>

                <div class="cms-field cms-form-full">
                    <label class="cms-label">Footer Mission Tagline</label>
                    <textarea name="footer_tagline" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('footer_tagline', '')); ?></textarea>
                </div>

                <div class="cms-field cms-form-full">
                    <label class="cms-label">Office Physical Address</label>
                    <textarea name="contact_address" class="cms-textarea" rows="2"><?php echo htmlspecialchars(cms_get('contact_address', '')); ?></textarea>
                </div>

                <div class="cms-field">
                    <label class="cms-label">Landline Telephone</label>
                    <input type="text" name="contact_telephone" class="cms-input" value="<?php echo htmlspecialchars(cms_get('contact_telephone', '(044) 958 2210')); ?>">
                </div>

                <div class="cms-field">
                    <label class="cms-label">Emergency Mobile Hotline</label>
                    <input type="text" name="contact_mobile" class="cms-input" value="<?php echo htmlspecialchars(cms_get('contact_mobile', '+63 (970) 300 8195')); ?>">
                </div>

                <div class="cms-field">
                    <label class="cms-label">Official CSWDO Email Address</label>
                    <input type="email" name="contact_email" class="cms-input" value="<?php echo htmlspecialchars(cms_get('contact_email', 'cswdo@palayancity.gov.ph')); ?>">
                </div>

                <div class="cms-field">
                    <label class="cms-label">Public Service Operating Hours</label>
                    <input type="text" name="office_hours" class="cms-input" value="<?php echo htmlspecialchars(cms_get('office_hours', 'Monday to Friday: 8:00 AM - 5:00 PM')); ?>">
                </div>

                <div class="cms-field cms-form-full">
                    <label class="cms-label">Official Facebook Page URL</label>
                    <input type="url" name="facebook_url" class="cms-input" value="<?php echo htmlspecialchars(cms_get('facebook_url', 'https://www.facebook.com/PalayanCSWDO')); ?>">
                </div>
            </div>
        </div>
    </form>

<?php endif; ?>

<script>
// ========== ACCORDION CONTROLS ==========
function toggleAcc(id) {
    const card = document.getElementById(id);
    if (!card) return;
    card.classList.toggle('active');
    card.classList.toggle('collapsed');
}

// ========== STEP SWITCHER FOR 4-STEP WORKFLOW ==========
function switchWfStep(stepNum) {
    document.querySelectorAll('.hero-step-tabs .workflow-step-tab').forEach((tab, idx) => {
        tab.classList.toggle('active', (idx + 1) === stepNum);
    });
    document.querySelectorAll('.wf-step-pane').forEach((pane, idx) => {
        pane.classList.toggle('active', (idx + 1) === stepNum);
    });
}

// ========== PROGRAM SHOWCASE INTERACTIVE LIVE PREVIEW & SLIDESHOW ==========
const progPvTimers = {};

function switchProgPane(progKey, btn) {
    document.querySelectorAll('.prog-switch-tabs .workflow-step-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.querySelectorAll('.prog-editor-pane').forEach(p => p.classList.remove('active'));
    const targetPane = document.getElementById('pane_' + progKey);
    if (targetPane) targetPane.classList.add('active');
    initPvSlideshow(progKey);
}

function initPvSlideshow(progKey) {
    if (progPvTimers[progKey]) clearInterval(progPvTimers[progKey]);
    const banner = document.getElementById('pv_banner_' + progKey);
    if (!banner) return;
    
    progPvTimers[progKey] = setInterval(() => {
        stepPvSlide(progKey, 1);
    }, 4000);

    banner.onmouseenter = () => clearInterval(progPvTimers[progKey]);
    banner.onmouseleave = () => {
        clearInterval(progPvTimers[progKey]);
        progPvTimers[progKey] = setInterval(() => stepPvSlide(progKey, 1), 4000);
    };
}

function switchPvSlide(progKey, slideIndex) {
    const slideshow = document.getElementById('pv_slideshow_' + progKey);
    const dotsContainer = document.getElementById('pv_dots_' + progKey);
    if (!slideshow) return;
    const slides = slideshow.querySelectorAll('.cms-prog-pv-slide');
    if (!slides.length) return;
    const idx = (slideIndex + slides.length) % slides.length;
    slides.forEach((s, i) => s.classList.toggle('active', i === idx));
    if (dotsContainer) {
        dotsContainer.querySelectorAll('.pv-dot').forEach((d, i) => d.classList.toggle('active', i === idx));
    }
}

function stepPvSlide(progKey, dir) {
    const slideshow = document.getElementById('pv_slideshow_' + progKey);
    if (!slideshow) return;
    const slides = slideshow.querySelectorAll('.cms-prog-pv-slide');
    if (!slides.length) return;
    let currentIdx = 0;
    slides.forEach((s, i) => { if (s.classList.contains('active')) currentIdx = i; });
    switchPvSlide(progKey, currentIdx + dir);
}

function reindexSlideBadges(progKey) {
    const list = document.getElementById('slides_list_' + progKey);
    if (!list) return;
    const rows = list.querySelectorAll('.cms-slide-row');
    rows.forEach((row, idx) => {
        const badge = row.querySelector('.cms-slide-badge');
        if (badge) {
            badge.innerHTML = 'Slide #' + (idx + 1) + (idx === 0 ? ' <span style="color:#059669;">(Cover)</span>' : '');
        }
        const replaceInput = row.querySelector('input[type="file"][name^="prog_"]');
        if (replaceInput) {
            replaceInput.name = `prog_${progKey}_slide_replace[${idx}]`;
        }
    });
    const countBadge = document.getElementById('badge_count_' + progKey);
    if (countBadge) {
        countBadge.textContent = rows.length + ' Slides in Carousel';
    }
}

function syncProgSlides(progKey) {
    const list = document.getElementById('slides_list_' + progKey);
    const slideshow = document.getElementById('pv_slideshow_' + progKey);
    const dotsContainer = document.getElementById('pv_dots_' + progKey);
    if (!list || !slideshow) return;
    
    const rows = list.querySelectorAll('.cms-slide-row');
    slideshow.innerHTML = '';
    if (dotsContainer) dotsContainer.innerHTML = '';

    rows.forEach((row, idx) => {
        const inp = row.querySelector('.slide-path-inp');
        const thumb = row.querySelector('.slide-thumb');
        let path = inp ? inp.value.trim() : '';
        let src = thumb ? thumb.src : '';
        if (path && (!src || src.includes('logo.png'))) {
            src = path.startsWith('http') || path.startsWith('data:') ? path : ('../' + path.replace(/^\.\//, ''));
            if (thumb) thumb.src = src;
        }

        const slide = document.createElement('div');
        slide.className = 'cms-prog-pv-slide' + (idx === 0 ? ' active' : '');
        if (src) slide.style.backgroundImage = 'url("' + src + '")';
        slideshow.appendChild(slide);

        if (dotsContainer) {
            const dot = document.createElement('span');
            dot.className = 'pv-dot' + (idx === 0 ? ' active' : '');
            dot.onclick = () => switchPvSlide(progKey, idx);
            dotsContainer.appendChild(dot);
        }
    });
}

function handleProgMultipleUpload(fileInput, progKey) {
    if (!fileInput.files || !fileInput.files.length) return;
    const list = document.getElementById('slides_list_' + progKey);
    if (!list) return;

    Array.from(fileInput.files).forEach((file, fIdx) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const dataUrl = e.target.result;
            const newRow = document.createElement('div');
            newRow.className = 'cms-slide-row';
            newRow.setAttribute('data-prog', progKey);
            newRow.style.cssText = 'display:flex; align-items:center; gap:12px; padding:10px 14px; background:rgba(16,185,129,0.04); border-radius:12px; border:1.5px solid rgba(16,185,129,0.3);';
            newRow.innerHTML = `
                <div class="cms-slide-badge" style="font-size:0.75rem; font-weight:700; color:var(--cms-maroon); background:rgba(127,3,3,0.08); padding:5px 10px; border-radius:20px; white-space:nowrap; min-width:85px; text-align:center;">
                    Slide #...
                </div>
                <img src="${dataUrl}" class="cms-img-thumb-preview slide-thumb" style="width:72px; height:48px; object-fit:cover; border-radius:6px; flex-shrink:0; border:1px solid rgba(0,0,0,0.08);" alt="New Upload Preview">
                <div style="flex:1; min-width:160px;">
                    <input type="text" name="prog_${progKey}_slides[]" class="cms-input slide-path-inp" value="[New Upload] ${file.name}" style="font-weight:600; color:#059669;" oninput="syncProgSlides('${progKey}')">
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <button type="button" class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px;" onclick="moveSlide('${progKey}', this, -1)" title="Move slide up">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button" class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px;" onclick="moveSlide('${progKey}', this, 1)" title="Move slide down">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <button type="button" class="cms-btn-danger" style="font-size:0.72rem; padding:6px 10px; color:#ef4444; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:6px; cursor:pointer;" onclick="removeSlide('${progKey}', this)" title="Delete slide">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            list.appendChild(newRow);
            reindexSlideBadges(progKey);
            syncProgSlides(progKey);
            // Switch preview to this newly added slide
            const totalSlides = list.querySelectorAll('.cms-slide-row').length;
            switchPvSlide(progKey, totalSlides - 1);
        };
        reader.readAsDataURL(file);
    });
}

function handleSingleSlideReplace(fileInput, progKey, rowEl) {
    if (!fileInput.files || !fileInput.files[0] || !rowEl) return;
    const file = fileInput.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const thumb = rowEl.querySelector('.slide-thumb');
        const inp = rowEl.querySelector('.slide-path-inp');
        if (thumb) thumb.src = e.target.result;
        if (inp) {
            inp.value = '[Replaced] ' + file.name;
            inp.style.color = '#059669';
            inp.style.fontWeight = '600';
        }
        syncProgSlides(progKey);
        const allRows = Array.from(rowEl.parentNode.children);
        const rowIdx = allRows.indexOf(rowEl);
        if (rowIdx !== -1) switchPvSlide(progKey, rowIdx);
    };
    reader.readAsDataURL(file);
}

function addSlideRow(progKey) {
    const list = document.getElementById('slides_list_' + progKey);
    if (!list) return;
    const count = list.querySelectorAll('.cms-slide-row').length + 1;
    const newRow = document.createElement('div');
    newRow.className = 'cms-slide-row';
    newRow.setAttribute('data-prog', progKey);
    newRow.style.cssText = 'display:flex; align-items:center; gap:12px; padding:10px 14px; background:rgba(0,0,0,0.02); border-radius:12px; border:1.5px solid var(--cms-border);';
    newRow.innerHTML = `
        <div class="cms-slide-badge" style="font-size:0.75rem; font-weight:700; color:var(--cms-maroon); background:rgba(127,3,3,0.08); padding:5px 10px; border-radius:20px; white-space:nowrap; min-width:85px; text-align:center;">
            Slide #${count}
        </div>
        <img src="../images/logo.png" class="cms-img-thumb-preview slide-thumb" style="width:72px; height:48px; object-fit:cover; border-radius:6px; flex-shrink:0; border:1px solid rgba(0,0,0,0.08);" alt="New Slide">
        <div style="flex:1; min-width:160px;">
            <input type="text" name="prog_${progKey}_slides[]" class="cms-input slide-path-inp" value="" oninput="syncProgSlides('${progKey}')" placeholder="images/photo.png or uploads/cms/...">
        </div>
        <div style="display:flex; align-items:center; gap:6px;">
            <label class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px; cursor:pointer;" title="Upload or replace picture">
                <i class="fas fa-upload"></i> Upload
                <input type="file" name="prog_${progKey}_slide_replace[${count - 1}]" accept="image/*" style="display:none;" onchange="handleSingleSlideReplace(this, '${progKey}', this.closest('.cms-slide-row'))">
            </label>
            <button type="button" class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px;" onclick="moveSlide('${progKey}', this, -1)" title="Move slide up">
                <i class="fas fa-arrow-up"></i>
            </button>
            <button type="button" class="cms-btn-secondary" style="font-size:0.72rem; padding:6px 10px;" onclick="moveSlide('${progKey}', this, 1)" title="Move slide down">
                <i class="fas fa-arrow-down"></i>
            </button>
            <button type="button" class="cms-btn-danger" style="font-size:0.72rem; padding:6px 10px; color:#ef4444; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.2); border-radius:6px; cursor:pointer;" onclick="removeSlide('${progKey}', this)" title="Delete slide">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    `;
    list.appendChild(newRow);
    reindexSlideBadges(progKey);
    syncProgSlides(progKey);
}

function removeSlide(progKey, btn) {
    const row = btn.closest('.cms-slide-row');
    if (!row) return;
    const list = row.parentNode;
    if (list.querySelectorAll('.cms-slide-row').length <= 1) {
        alert('Each program card must have at least one slide picture.');
        return;
    }
    row.remove();
    reindexSlideBadges(progKey);
    syncProgSlides(progKey);
}

function moveSlide(progKey, btn, direction) {
    const row = btn.closest('.cms-slide-row');
    if (!row) return;
    if (direction === -1 && row.previousElementSibling) {
        row.parentNode.insertBefore(row, row.previousElementSibling);
    } else if (direction === 1 && row.nextElementSibling) {
        row.parentNode.insertBefore(row.nextElementSibling, row);
    }
    reindexSlideBadges(progKey);
    syncProgSlides(progKey);
}

function syncProgPreview(progKey) {
    const titleInp = document.getElementById('inp_prog_' + progKey + '_title');
    const badgeInp = document.getElementById('inp_prog_' + progKey + '_badge');
    const captionInp = document.getElementById('inp_prog_' + progKey + '_caption');
    const descInp = document.getElementById('inp_prog_' + progKey + '_desc');
    const btn1Inp = document.getElementById('inp_prog_' + progKey + '_btn1_text');
    const btn2Inp = document.getElementById('inp_prog_' + progKey + '_btn2_text');

    const pvTitle = document.getElementById('pv_title_' + progKey);
    const pvBadge = document.getElementById('pv_badge_' + progKey);
    const pvCaption = document.getElementById('pv_caption_' + progKey);
    const pvDesc = document.getElementById('pv_desc_' + progKey);
    const pvBtn1 = document.getElementById('pv_btn1_' + progKey);
    const pvBtn2 = document.getElementById('pv_btn2_' + progKey);

    if (titleInp && pvTitle) pvTitle.textContent = titleInp.value.trim() || 'Program Title';
    if (badgeInp && pvBadge) {
        pvBadge.innerHTML = '<i class="fas fa-check-circle"></i> ' + (badgeInp.value.trim() || 'Service Track');
    }
    if (captionInp && pvCaption) {
        const currentIcon = pvCaption.querySelector('i');
        const iconHtml = currentIcon ? currentIcon.outerHTML + ' ' : '';
        pvCaption.innerHTML = iconHtml + (captionInp.value.trim() || '');
    }
    if (descInp && pvDesc) pvDesc.textContent = descInp.value.trim() || '';
    if (btn1Inp && pvBtn1) pvBtn1.textContent = btn1Inp.value.trim() || 'Learn More';
    if (btn2Inp && pvBtn2) pvBtn2.innerHTML = (btn2Inp.value.trim() || 'Apply') + ' &rarr;';
}

function syncProgPills(progKey) {
    const pillsInp = document.getElementById('inp_prog_' + progKey + '_subservices');
    const pillsContainer = document.getElementById('pv_pills_' + progKey);
    if (!pillsInp || !pillsContainer) return;
    const items = pillsInp.value.split(',').map(s => s.trim()).filter(s => s.length > 0);
    pillsContainer.innerHTML = '';
    items.forEach(item => {
        const span = document.createElement('span');
        span.className = 'cms-prog-pv-pill';
        span.textContent = item;
        pillsContainer.appendChild(span);
    });
}

// Auto-start housing preview slideshow on load
document.addEventListener('DOMContentLoaded', () => {
    initPvSlideshow('housing');
});

// ========== REAL-TIME INTERACTIVE HERO LIVE PREVIEW ==========
(function initHeroLivePreview() {
    const badgeInp = document.getElementById('inp_hero_badge');
    const headlineInp = document.getElementById('inp_hero_headline');
    const subheadlineInp = document.getElementById('inp_hero_subheadline');
    const descInp = document.getElementById('inp_hero_description');
    const btn1Inp = document.getElementById('inp_hero_btn1_text');
    const btn2Inp = document.getElementById('inp_hero_btn2_text');
    const imageInp = document.getElementById('inp_hero_image');
    const fileInp = document.getElementById('inp_hero_file');

    const pvBadge = document.getElementById('pvBadge');
    const pvHeadline = document.getElementById('pvHeadline');
    const pvSubheadline = document.getElementById('pvSubheadline');
    const pvDesc = document.getElementById('pvDesc');
    const pvBtn1 = document.getElementById('pvBtn1');
    const pvBtn2 = document.getElementById('pvBtn2');
    const pvImage = document.getElementById('pvImage');
    const thumbHero = document.getElementById('thumb_hero_image');
    const fileNameDisplay = document.getElementById('file_hero_name');

    if (badgeInp && pvBadge) {
        badgeInp.addEventListener('input', () => {
            pvBadge.innerHTML = '<i class="fas fa-star"></i> ' + (badgeInp.value.trim() || 'CSWDO Palayan City');
        });
    }
    if (headlineInp && pvHeadline) {
        headlineInp.addEventListener('input', () => {
            pvHeadline.textContent = headlineInp.value.trim() || 'SERVING PALAYAN CITY WITH EXCELLENCE';
        });
    }
    if (subheadlineInp && pvSubheadline) {
        subheadlineInp.addEventListener('input', () => {
            pvSubheadline.textContent = subheadlineInp.value.trim() || 'Streamlined Social Services For Every Palayan Resident';
        });
    }
    if (descInp && pvDesc) {
        descInp.addEventListener('input', () => {
            pvDesc.textContent = descInp.value.trim() || 'Schedule appointments, track assistance requests, and access housing programs...';
        });
    }
    if (btn1Inp && pvBtn1) {
        btn1Inp.addEventListener('input', () => {
            pvBtn1.innerHTML = (btn1Inp.value.trim() || 'Book Appointment Now') + ' &rarr;';
        });
    }
    if (btn2Inp && pvBtn2) {
        btn2Inp.addEventListener('input', () => {
            pvBtn2.textContent = btn2Inp.value.trim() || 'Track My Request';
        });
    }
    if (imageInp && pvImage) {
        imageInp.addEventListener('input', () => {
            const path = imageInp.value.trim();
            if (path) {
                const src = path.startsWith('http') ? path : ('../' + path.replace(/^\.\//, ''));
                pvImage.src = src;
                if (thumbHero) thumbHero.src = src;
            }
        });
    }
    if (fileInp && pvImage) {
        fileInp.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const f = this.files[0];
                if (fileNameDisplay) fileNameDisplay.textContent = 'Selected: ' + f.name;
                const reader = new FileReader();
                reader.onload = function(e) {
                    pvImage.src = e.target.result;
                    if (thumbHero) thumbHero.src = e.target.result;
                };
                reader.readAsDataURL(f);
            }
        });
    }

    // Video live preview listener
    const vidFileInp = document.getElementById('inp_housing_vid_file');
    const vidNameDisplay = document.getElementById('file_vid_name');
    const vidPlayer = document.getElementById('previewVideoPlayer');
    if (vidFileInp && vidPlayer) {
        vidFileInp.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const f = this.files[0];
                if (vidNameDisplay) vidNameDisplay.textContent = 'Selected video: ' + f.name + ' (' + Math.round(f.size/1048576) + 'MB)';
                const url = URL.createObjectURL(f);
                vidPlayer.src = url;
                vidPlayer.load();
            }
        });
    }
})();

// Enhanced Toast Alert
function showCmsToast(message) {
    let toast = document.getElementById('cmsToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'cmsToast';
        toast.className = 'cms-toast';
        document.body.appendChild(toast);
    }
    toast.innerHTML = '<i class="fas fa-check-circle" style="color:#10B981;"></i> <span>' + message + '</span>';
    toast.classList.add('show');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => {
        toast.classList.remove('show');
    }, 2800);
}

// Copy Media Asset Path with visual toast
function copyMediaAssetPath(path) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(path).then(function() {
            showCmsToast('Copied: "' + path + '"');
        }).catch(function() {
            fallbackPrompt(path);
        });
    } else {
        fallbackPrompt(path);
    }
}
function fallbackPrompt(path) {
    prompt('Copy asset path:', path);
}
function copyPath(path) {
    copyMediaAssetPath(path);
}

// ========== SERVICES FILTERING ==========
function filterServicesTable() {
    const searchVal = (document.getElementById('serviceSearchInput')?.value || '').toLowerCase().trim();
    const catVal = (document.getElementById('serviceCategoryFilter')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#servicesTableBody .service-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowName = row.getAttribute('data-name') || '';
        const rowCat = row.getAttribute('data-cat') || '';
        const matchSearch = !searchVal || rowName.includes(searchVal);
        const matchCat = !catVal || rowCat.includes(catVal);

        if (matchSearch && matchCat) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noRow = document.getElementById('noServicesFoundRow');
    if (noRow) {
        noRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
}

// ========== AICS REQUIREMENTS FILTERING ==========
let currentReqTypeFilter = 'all';

function filterAicsReqs(type, btn) {
    currentReqTypeFilter = (type || 'all').toLowerCase();
    if (btn) {
        document.querySelectorAll('.cms-pill-filter-bar .cms-filter-pill-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    applyReqsFilters();
}

function filterReqsSearch() {
    applyReqsFilters();
}

function applyReqsFilters() {
    const searchVal = (document.getElementById('reqSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#reqsTableBody .req-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowType = row.getAttribute('data-type') || '';
        const rowSearch = row.getAttribute('data-search') || '';
        const matchType = (currentReqTypeFilter === 'all') || (rowType === currentReqTypeFilter);
        const matchSearch = !searchVal || rowSearch.includes(searchVal);

        if (matchType && matchSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noRow = document.getElementById('noReqsFoundRow');
    if (noRow) {
        noRow.style.display = (visibleCount === 0) ? '' : 'none';
    }
}

// ========== SECTION VISIBILITY CONTROLS ==========
function toggleSectionStatus(checkbox, statusId) {
    const label = document.getElementById(statusId);
    if (!label) return;
    if (checkbox.checked) {
        label.className = 'cms-vis-status-text visible';
        label.innerHTML = '<i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> Live on Homepage';
    } else {
        label.className = 'cms-vis-status-text hidden';
        label.innerHTML = '<i class="fas fa-circle" style="font-size:0.5rem; margin-right:4px;"></i> Hidden from Public';
    }
}

function setAllSections(visible) {
    const form = document.getElementById('visibilityForm');
    if (!form) return;
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => {
        cb.checked = visible;
        const name = cb.getAttribute('name');
        if (name) {
            toggleSectionStatus(cb, 'status_' + name);
        }
    });
    showCmsToast(visible ? 'All sections marked as Live on Homepage' : 'All sections marked as Hidden');
}

// ========== MEDIA ASSETS GALLERY FILTERING & DROPZONE ==========
let currentMediaCatFilter = 'all';

function filterMediaGalleryCategory(cat, btn) {
    currentMediaCatFilter = (cat || 'all').toLowerCase();
    if (btn) {
        document.querySelectorAll('.cms-accordion-card .cms-pill-filter-bar .cms-filter-pill-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    applyMediaFilters();
}

function filterMediaGallerySearch() {
    applyMediaFilters();
}

function applyMediaFilters() {
    const searchVal = (document.getElementById('mediaSearchInput')?.value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('#mediaAssetsGrid .media-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const cardCat = card.getAttribute('data-cat') || '';
        const cardName = card.getAttribute('data-name') || '';
        const matchCat = (currentMediaCatFilter === 'all') || (cardCat === currentMediaCatFilter);
        const matchSearch = !searchVal || cardName.includes(searchVal);

        if (matchCat && matchSearch) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const noMsg = document.getElementById('noMediaFoundMsg');
    if (noMsg) {
        noMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}

function handleMediaFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const emptyState = document.getElementById('dropzoneEmptyState');
        const selectedState = document.getElementById('dropzoneSelectedState');
        const previewImg = document.getElementById('uploadPreviewImg');
        const nameEl = document.getElementById('uploadFileName');
        const sizeEl = document.getElementById('uploadFileSize');

        if (nameEl) nameEl.textContent = file.name;
        if (sizeEl) sizeEl.textContent = (file.size / 1024 > 1024 ? (file.size / 1048576).toFixed(2) + ' MB' : (file.size / 1024).toFixed(1) + ' KB') + ' • ' + (file.type || 'Media File');

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewImg) previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            if (previewImg) previewImg.src = '../images/logo.png';
        }

        if (emptyState) emptyState.style.display = 'none';
        if (selectedState) selectedState.style.display = 'flex';
    }
}

// Service modal functions
function openServiceModal() {
    document.getElementById('serviceModalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color:var(--cms-maroon);"></i> Add New Service';
    document.getElementById('svc_id').value = '0';
    document.getElementById('svc_name').value = '';
    document.getElementById('svc_code').value = '';
    document.getElementById('svc_cat').value = 'General';
    document.getElementById('svc_badge').value = '';
    document.getElementById('svc_icon').value = 'fas fa-hands-helping';
    document.getElementById('svc_sort').value = '0';
    document.getElementById('svc_sdesc').value = '';
    document.getElementById('svc_desc').value = '';
    document.getElementById('svc_elig').value = '';
    document.getElementById('svc_req').value = '';
    document.getElementById('svc_proc').value = '';
    document.getElementById('svc_proc_time').value = '1-3 working days';
    document.getElementById('svc_fees').value = 'Free of Charge';
    document.getElementById('svc_where_to').value = 'CSWDO Desk, Palayan City Hall';
    document.getElementById('svc_apply_url').value = '';
    document.getElementById('svc_active').checked = true;
    document.getElementById('svc_featured').checked = true;
    document.getElementById('serviceModal').classList.add('open');
}

function closeServiceModal() {
    document.getElementById('serviceModal').classList.remove('open');
}

function editService(svc) {
    document.getElementById('serviceModalTitle').innerHTML = '<i class="fas fa-edit" style="color:var(--cms-maroon);"></i> Edit Service #' + svc.service_id;
    document.getElementById('svc_id').value = svc.service_id;
    document.getElementById('svc_name').value = svc.service_name || '';
    document.getElementById('svc_code').value = svc.service_code || '';
    document.getElementById('svc_cat').value = svc.category || 'General';
    document.getElementById('svc_badge').value = svc.badge || '';
    document.getElementById('svc_icon').value = svc.icon || 'fas fa-hands-helping';
    document.getElementById('svc_sort').value = svc.sort_order || 0;
    document.getElementById('svc_sdesc').value = svc.short_desc || '';
    document.getElementById('svc_desc').value = svc.description || '';
    document.getElementById('svc_elig').value = svc.eligibility || '';
    document.getElementById('svc_req').value = svc.requirements || '';
    document.getElementById('svc_proc').value = svc.procedure || '';
    document.getElementById('svc_proc_time').value = svc.processing_time || '';
    document.getElementById('svc_fees').value = svc.fees || 'Free of Charge';
    document.getElementById('svc_where_to').value = svc.where_to_apply || '';
    document.getElementById('svc_apply_url').value = svc.apply_url || '';
    document.getElementById('svc_active').checked = (parseInt(svc.is_active) === 1);
    document.getElementById('svc_featured').checked = (parseInt(svc.is_featured) === 1);
    document.getElementById('serviceModal').classList.add('open');
}

// Requirement modal functions
function openReqModal() {
    document.getElementById('reqModalTitle').innerText = 'Add AICS Requirement';
    document.getElementById('modal_req_id').value = '0';
    document.getElementById('modal_doc_name').value = '';
    document.getElementById('modal_doc_code').value = '';
    document.getElementById('modal_doc_desc').value = '';
    document.getElementById('modal_doc_sort').value = '0';
    document.getElementById('modal_doc_req').checked = true;
    document.getElementById('reqModal').classList.add('open');
}

function closeReqModal() {
    document.getElementById('reqModal').classList.remove('open');
}

function editReq(rq) {
    document.getElementById('reqModalTitle').innerText = 'Edit Requirement #' + rq.req_id;
    document.getElementById('modal_req_id').value = rq.req_id;
    document.getElementById('modal_req_type').value = rq.request_type;
    document.getElementById('modal_doc_name').value = rq.document_name;
    document.getElementById('modal_doc_code').value = rq.document_code;
    document.getElementById('modal_doc_desc').value = rq.description || '';
    document.getElementById('modal_doc_sort').value = rq.sort_order || 0;
    document.getElementById('modal_doc_req').checked = (parseInt(rq.is_required) === 1);
    document.getElementById('reqModal').classList.add('open');
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('cms-modal')) {
        e.target.classList.remove('open');
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
