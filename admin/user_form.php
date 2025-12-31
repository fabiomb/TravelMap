<?php
/**
 * Formulario de Usuario
 * 
 * Crear o editar un usuario
 */

// Cargar configuración y dependencias ANTES de header.php para permitir redirects
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// SEGURIDAD: Validar autenticación ANTES de cualquier procesamiento
require_auth();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/models/User.php';

$userModel = new User();
$errors = [];
$success = false;
$user = null;
$is_edit = false;

// Verificar si es edición
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    $user = $userModel->getById($user_id);
    
    if (!$user) {
        header('Location: users.php');
        exit;
    }
    $is_edit = true;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id' => $is_edit ? $user_id : null,
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? ''
    ];

    // Validar datos
    $errors = $userModel->validate($data, $is_edit);

    if (empty($errors)) {
        // Preparar datos para guardar
        $saveData = [
            'username' => $data['username']
        ];

        // Hashear contraseña si se proporcionó
        if (!empty($data['password'])) {
            $saveData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($is_edit) {
            // Actualizar
            if ($userModel->update($user_id, $saveData)) {
                $_SESSION['success_message'] = __('users.updated_success');
                header("Location: users.php");
                exit;
            } else {
                $errors['general'] = __('users.error_saving');
            }
        } else {
            // Crear
            $new_id = $userModel->create($saveData);
            if ($new_id) {
                $_SESSION['success_message'] = __('users.created_success');
                header("Location: users.php");
                exit;
            } else {
                $errors['general'] = __('users.error_saving');
            }
        }
    }
}

// Ahora sí incluir header.php (después de procesar y posibles redirects)
require_once __DIR__ . '/../includes/header.php';

// Valores por defecto para formulario
$form_data = [
    'username' => $is_edit ? $user['username'] : ($_POST['username'] ?? ''),
];
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-<?= $is_edit ? 'pencil' : 'person-plus' ?> me-2" viewBox="0 0 16 16">
                <?php if ($is_edit): ?>
                    <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/>
                <?php else: ?>
                    <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H1s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C9.516 10.68 8.289 10 6 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/>
                    <path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5"/>
                <?php endif; ?>
            </svg>
            <?= $is_edit ? __('users.edit_user') : __('users.new_user') ?>
        </h1>
    </div>
    <div class="col-md-4 text-end">
        <a href="users.php" class="btn btn-outline-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-left me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8"/>
            </svg>
            <?= __('common.back_to_list') ?>
        </a>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle me-2" viewBox="0 0 16 16">
            <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
            <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
        </svg>
        <?= htmlspecialchars($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-person me-2" viewBox="0 0 16 16">
                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 5-4 5 3 5 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664"/>
                </svg>
                <?= __('users.user_info') ?>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Nombre de usuario -->
                    <div class="mb-3">
                        <label for="username" class="form-label"><?= __('users.username') ?> <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
                                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 5-4 5 3 5 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664"/>
                                </svg>
                            </span>
                            <input type="text" 
                                   class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                                   id="username" 
                                   name="username" 
                                   value="<?= htmlspecialchars($form_data['username']) ?>" 
                                   required 
                                   maxlength="50"
                                   placeholder="<?= __('forms.example') ?>: usuario123"
                                   autocomplete="username">
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!isset($errors['username'])): ?>
                            <div class="form-text"><?= __('forms.only_letters_numbers') ?>. <?= __('forms.minimum') ?> 3 <?= __('forms.characters') ?>.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Contraseña -->
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <?= __('users.password') ?> 
                            <?php if (!$is_edit): ?>
                                <span class="text-danger">*</span>
                            <?php else: ?>
                                <span class="text-muted small">(<?= __('forms.leave_blank_keep_password') ?>)</span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock" viewBox="0 0 16 16">
                                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2M5 8h6a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1"/>
                                </svg>
                            </span>
                            <input type="password" 
                                   class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                   id="password" 
                                   name="password" 
                                   <?= !$is_edit ? 'required' : '' ?>
                                   minlength="6"
                                   placeholder="<?= $is_edit ? __('forms.new_password_optional') : __('forms.minimum') . ' 6 ' . __('forms.characters') ?>"
                                   autocomplete="new-password">
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Confirmar contraseña -->
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">
                            <?= __('users.confirm_password') ?> 
                            <?php if (!$is_edit): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lock-fill" viewBox="0 0 16 16">
                                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/>
                                </svg>
                            </span>
                            <input type="password" 
                                   class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" 
                                   id="password_confirm" 
                                   name="password_confirm" 
                                   <?= !$is_edit ? 'required' : '' ?>
                                   minlength="6"
                                   placeholder="<?= __('forms.repeat_password') ?>"
                                   autocomplete="new-password">
                            <?php if (isset($errors['password_confirm'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['password_confirm']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="users.php" class="btn btn-secondary"><?= __('common.cancel') ?></a>
                        <button type="submit" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-save me-1" viewBox="0 0 16 16">
                                <path d="M2 1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H9.5a1 1 0 0 0-1 1v7.293l2.646-2.647a.5.5 0 0 1 .708.708l-3.5 3.5a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L7.5 9.293V2a2 2 0 0 1 2-2H14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h2.5a.5.5 0 0 1 0 1z"/>
                            </svg>
                            <?= $is_edit ? __('forms.save_changes') : __('users.new_user') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                        <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533z"/>
                        <path d="M9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
                    </svg>
                    <?= __('forms.information') ?>
                </h5>
            </div>
            <div class="card-body">
                <h6><?= __('forms.required_fields') ?></h6>
                <ul>
                    <li><strong><?= __('users.username') ?>:</strong> <?= __('forms.unique_identifier') ?></li>
                    <li><strong><?= __('users.password') ?>:</strong> 
                        <?= $is_edit ? __('forms.only_if_change') : __('forms.required_for_new') ?>
                    </li>
                </ul>

                <h6 class="mt-3"><?= __('forms.password_requirements') ?></h6>
                <ul>
                    <li><?= __('forms.minimum') ?> 6 <?= __('forms.characters') ?></li>
                    <li><?= __('forms.passwords_encrypted') ?></li>
                    <li><?= __('forms.cannot_recover_password') ?></li>
                </ul>

                <h6 class="mt-3"><?= __('forms.username_requirements') ?></h6>
                <ul>
                    <li><?= __('forms.only_letters_numbers') ?></li>
                    <li><?= __('forms.minimum') ?> 3 <?= __('forms.characters') ?>, <?= __('forms.maximum') ?> 50</li>
                    <li><?= __('forms.must_be_unique') ?></li>
                    <li><?= __('forms.used_for_login') ?></li>
                </ul>

                <?php if ($is_edit && $user_id === get_current_user_id()): ?>
                    <div class="alert alert-warning alert-permanent mt-3 mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle me-2" viewBox="0 0 16 16">
                            <path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/>
                            <path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
                        </svg>
                        <strong><?= __('common.note') ?>:</strong> <?= __('forms.note_own_user_warning') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
