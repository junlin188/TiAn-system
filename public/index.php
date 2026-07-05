<?php
declare(strict_types=1);

session_start();

const APP_NAME = '星闪提案系统';
const BASE_DIR = __DIR__ . '/..';
const STORAGE_DIR = BASE_DIR . '/storage';
const UPLOAD_DIR = STORAGE_DIR . '/uploads';
const DB_FILE = STORAGE_DIR . '/app.sqlite';

date_default_timezone_set('Asia/Shanghai');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR, 0775, true);
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_db($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS workgroups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT DEFAULT ''
        );
        CREATE TABLE IF NOT EXISTS member_units (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workgroup_id INTEGER NOT NULL,
            company_name TEXT NOT NULL,
            remark TEXT DEFAULT '',
            FOREIGN KEY(workgroup_id) REFERENCES workgroups(id)
        );
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            real_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('super_admin','admin','chief','member')),
            status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','pending','disabled')),
            workgroup_id INTEGER,
            member_unit_id INTEGER,
            created_at TEXT NOT NULL,
            FOREIGN KEY(workgroup_id) REFERENCES workgroups(id),
            FOREIGN KEY(member_unit_id) REFERENCES member_units(id)
        );
        CREATE TABLE IF NOT EXISTS directories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER,
            name TEXT NOT NULL,
            path TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL,
            FOREIGN KEY(parent_id) REFERENCES directories(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            directory_id INTEGER NOT NULL,
            proposal_id INTEGER,
            uploader_id INTEGER,
            original_name TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            size INTEGER NOT NULL,
            mime_type TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(directory_id) REFERENCES directories(id),
            FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE SET NULL,
            FOREIGN KEY(uploader_id) REFERENCES users(id) ON DELETE SET NULL
        );
        CREATE TABLE IF NOT EXISTS proposals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_date TEXT NOT NULL,
            meeting_place TEXT NOT NULL,
            meeting_subject TEXT NOT NULL,
            workgroup_id INTEGER NOT NULL,
            member_unit_id INTEGER NOT NULL,
            chief_user_id INTEGER NOT NULL,
            meeting_code TEXT NOT NULL,
            proposal_code TEXT NOT NULL UNIQUE,
            directory_id INTEGER NOT NULL,
            due_date TEXT NOT NULL,
            description TEXT DEFAULT '',
            created_at TEXT NOT NULL,
            FOREIGN KEY(workgroup_id) REFERENCES workgroups(id),
            FOREIGN KEY(member_unit_id) REFERENCES member_units(id),
            FOREIGN KEY(chief_user_id) REFERENCES users(id),
            FOREIGN KEY(directory_id) REFERENCES directories(id)
        );
        CREATE TABLE IF NOT EXISTS directory_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            directory_id INTEGER NOT NULL,
            UNIQUE(user_id, directory_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(directory_id) REFERENCES directories(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS proposal_uploads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            proposal_id INTEGER NOT NULL,
            file_id INTEGER NOT NULL,
            uploader_id INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
            FOREIGN KEY(file_id) REFERENCES files(id) ON DELETE CASCADE,
            FOREIGN KEY(uploader_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");

    ensure_super_admin_support($pdo);

    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $now = now();
    $groups = [
        ['需求和标准组', 'SparkLink相关标准制定'],
        ['频谱组', 'SparkLink频谱需求，推动频谱试验和分配'],
        ['测试认证组', 'SparkLink测试实验室、授权、认证等工作'],
        ['安全组', '研究星闪技术应用场景的安全需求和安全方案'],
        ['智能家居产业推广组', '研究SparkLink在智能家居领域的需求及解决方案'],
        ['星闪联盟', '-'],
    ];
    $stmt = $pdo->prepare('INSERT INTO workgroups(name, description) VALUES(?, ?)');
    foreach ($groups as $g) {
        $stmt->execute($g);
    }

    $wgReq = id_by_name('workgroups', 'name', '需求和标准组');
    $wgSafe = id_by_name('workgroups', 'name', '安全组');
    $wgHome = id_by_name('workgroups', 'name', '智能家居产业推广组');
    $wgAlliance = id_by_name('workgroups', 'name', '星闪联盟');
    $unitStmt = $pdo->prepare('INSERT INTO member_units(workgroup_id, company_name, remark) VALUES(?, ?, ?)');
    foreach ([
        [$wgReq, '中国信息通信研究院', '负责需求分析和标准制定'],
        [$wgSafe, '深圳市宝安区', '安全组会员单位'],
        [$wgHome, '深圳市炎枫科技有限公司', '智能家居会员单位'],
        [$wgReq, '秘书处', '系统管理单位'],
        [$wgAlliance, '星闪联盟', '超管默认会员单位'],
    ] as $unit) {
        $unitStmt->execute($unit);
    }

    $dirs = [
        ['ISLA', null],
        ['0809', 'ISLA'],
        ['080911', 'ISLA'],
        ['Academic Committee', 'ISLA'],
        ['Board', 'ISLA'],
        ['B#1_2009', 'ISLA/Board'],
        ['Expert Committee', 'ISLA'],
        ['EC#1 2103', 'ISLA/Expert Committee'],
        ['Spec', 'ISLA'],
        ['Tdoc', 'ISLA'],
        ['IPG', 'ISLA/Tdoc'],
        ['Smart Home', 'ISLA/Tdoc/IPG'],
        ['Smart Manufacture', 'ISLA/Tdoc/IPG'],
        ['Smart Terminal', 'ISLA/Tdoc/IPG'],
        ['Smart Vehicle', 'ISLA/Tdoc/IPG'],
        ['TWG', 'ISLA'],
        ['Cyber Security & Safety', 'ISLA/TWG'],
        ['Frequency Spectrum', 'ISLA/TWG'],
        ['Req.&Standard', 'ISLA/TWG'],
        ['S#15_2503', 'ISLA/TWG/Req.&Standard'],
        ['S#16_2505', 'ISLA/TWG/Req.&Standard'],
        ['Testing&Certification', 'ISLA/TWG'],
        ['test0808', 'ISLA'],
    ];
    foreach ($dirs as [$name, $parentPath]) {
        create_directory_seed($name, $parentPath);
    }

    $adminUnit = id_by_name('member_units', 'company_name', '星闪联盟');
    $safeUnit = id_by_name('member_units', 'company_name', '深圳市宝安区');
    $reqUnit = id_by_name('member_units', 'company_name', '中国信息通信研究院');
    $userStmt = $pdo->prepare('
        INSERT INTO users(username, email, real_name, password_hash, role, status, workgroup_id, member_unit_id, created_at)
        VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $userStmt->execute(['admin', 'admin@example.com', '管理员', password_hash('admin123456', PASSWORD_DEFAULT), 'super_admin', 'active', $wgReq, $adminUnit, $now]);
    $userStmt->execute(['shouxi2', '234567@qq.com', '测试首席代表', password_hash('chief123456', PASSWORD_DEFAULT), 'chief', 'active', $wgSafe, $safeUnit, $now]);
    $userStmt->execute(['member1', 'member@test.com', '普通会员', password_hash('member123456', PASSWORD_DEFAULT), 'member', 'active', $wgReq, $reqUnit, $now]);

    $root = id_by_path('ISLA');
    $tdoc = id_by_path('ISLA/Tdoc');
    $test = id_by_path('ISLA/test0808');
    $admin = id_by_name('users', 'username', 'admin');
    $chief = id_by_name('users', 'username', 'shouxi2');
    $member = id_by_name('users', 'username', 'member1');
    grant_all_dirs($admin);
    grant_dir($chief, $root);
    grant_dir($member, $tdoc);

    $proposalStmt = $pdo->prepare('
        INSERT INTO proposals(meeting_date, meeting_place, meeting_subject, workgroup_id, member_unit_id, chief_user_id, meeting_code, proposal_code, directory_id, due_date, description, created_at)
        VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $proposalStmt->execute(['2026-08-09', '深圳市宝安区', '3463734', $wgSafe, $safeUnit, $chief, '258258', '534623634', $test, '2026-08-16', '示例提案任务', $now]);
    $proposalStmt->execute(['2025-07-09', '3252', '253523', $wgSafe, $safeUnit, $chief, '123456', '123456', $tdoc, '2025-07-24', '已过期示例', $now]);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function id_by_name(string $table, string $field, string $value): int
{
    $stmt = db()->prepare("SELECT id FROM {$table} WHERE {$field} = ?");
    $stmt->execute([$value]);
    return (int)$stmt->fetchColumn();
}

function id_by_path(string $path): int
{
    $stmt = db()->prepare('SELECT id FROM directories WHERE path = ?');
    $stmt->execute([$path]);
    return (int)$stmt->fetchColumn();
}

function create_directory_seed(string $name, ?string $parentPath): void
{
    $parentId = $parentPath ? id_by_path($parentPath) : null;
    $path = $parentPath ? $parentPath . '/' . $name : $name;
    $stmt = db()->prepare('INSERT OR IGNORE INTO directories(parent_id, name, path, created_at) VALUES(?, ?, ?, ?)');
    $stmt->execute([$parentId, $name, $path, now()]);
}

function ensure_super_admin_support(PDO $pdo): void
{
    $sql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
    if ($sql !== '' && !str_contains($sql, "'super_admin'")) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();
        try {
            $pdo->exec("
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    real_name TEXT NOT NULL,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL CHECK(role IN ('super_admin','admin','chief','member')),
                    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','pending','disabled')),
                    workgroup_id INTEGER,
                    member_unit_id INTEGER,
                    created_at TEXT NOT NULL,
                    FOREIGN KEY(workgroup_id) REFERENCES workgroups(id),
                    FOREIGN KEY(member_unit_id) REFERENCES member_units(id)
                );
                INSERT INTO users_new(id, username, email, real_name, password_hash, role, status, workgroup_id, member_unit_id, created_at)
                    SELECT id, username, email, real_name, password_hash, role, status, workgroup_id, member_unit_id, created_at FROM users;
                DROP TABLE users;
                ALTER TABLE users_new RENAME TO users;
            ");
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        ensure_alliance_member_unit($pdo);
        ensure_admin_is_super_admin($pdo);
    }
}

function ensure_alliance_member_unit(PDO $pdo): int
{
    $stmt = $pdo->prepare('SELECT id FROM member_units WHERE company_name = ?');
    $stmt->execute(['星闪联盟']);
    $id = (int)$stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $groupStmt = $pdo->prepare('SELECT id FROM workgroups WHERE name = ?');
    $groupStmt->execute(['星闪联盟']);
    $workgroupId = (int)$groupStmt->fetchColumn();
    if ($workgroupId <= 0) {
        $pdo->prepare('INSERT INTO workgroups(name, description) VALUES(?, ?)')->execute(['星闪联盟', '-']);
        $workgroupId = (int)$pdo->lastInsertId();
    }
    $pdo->prepare('INSERT INTO member_units(workgroup_id, company_name, remark) VALUES(?, ?, ?)')
        ->execute([$workgroupId, '星闪联盟', '超管默认会员单位']);
    return (int)$pdo->lastInsertId();
}

function ensure_admin_is_super_admin(PDO $pdo): void
{
    $unitId = ensure_alliance_member_unit($pdo);
    $stmt = $pdo->prepare('UPDATE users SET role = ?, status = ?, member_unit_id = ? WHERE username = ?');
    $stmt->execute(['super_admin', 'active', $unitId, 'admin']);
    $adminId = (int)$pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    if ($adminId > 0) {
        grant_all_dirs($adminId);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT u.*, w.name AS workgroup_name, m.company_name FROM users u LEFT JOIN workgroups w ON w.id=u.workgroup_id LEFT JOIN member_units m ON m.id=u.member_unit_id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('?page=login');
    }
    return $user;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function js(?string $value): string
{
    return (string)json_encode((string)$value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function json_attr(array $value): string
{
    return e((string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
}

function is_admin(array $user): bool
{
    return in_array($user['role'], ['super_admin', 'admin'], true);
}

function is_super_admin(array $user): bool
{
    return $user['role'] === 'super_admin';
}

function can_manage_user(array $operator, array $target): bool
{
    if (is_super_admin($operator)) {
        return true;
    }
    return is_admin($operator) && in_array($target['role'], ['chief', 'member'], true);
}

function can_assign_user_role(array $operator, string $role): bool
{
    if (is_super_admin($operator)) {
        return in_array($role, ['super_admin', 'admin', 'chief', 'member'], true);
    }
    return is_admin($operator) && in_array($role, ['chief', 'member'], true);
}

function is_chief(array $user): bool
{
    return $user['role'] === 'chief';
}

function can_view_dir(array $user, int $dirId): bool
{
    if (is_admin($user)) {
        return true;
    }
    return in_array($dirId, allowed_dir_ids($user), true);
}

function allowed_dir_ids(array $user): array
{
    static $cache = [];
    $key = (string)$user['id'];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $stmt = db()->prepare('SELECT directory_id FROM directory_permissions WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $cache[$key] = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    return $cache[$key];
}

function visible_dir_ids(array $user): array
{
    $allowed = allowed_dir_ids($user);
    $visible = $allowed;
    foreach ($allowed as $dirId) {
        $visible = array_merge($visible, ancestor_dir_ids($dirId));
    }
    return array_values(array_unique($visible));
}

function ancestor_dir_ids(int $dirId): array
{
    $ids = [];
    $stmt = db()->prepare('SELECT parent_id FROM directories WHERE id = ?');
    while ($dirId > 0) {
        $stmt->execute([$dirId]);
        $parentId = (int)$stmt->fetchColumn();
        if ($parentId <= 0) {
            break;
        }
        $ids[] = $parentId;
        $dirId = $parentId;
    }
    return $ids;
}

function descendant_dir_ids(int $dirId): array
{
    $ids = [];
    $children = db()->prepare('SELECT id FROM directories WHERE parent_id = ? ORDER BY name');
    $children->execute([$dirId]);
    foreach ($children->fetchAll(PDO::FETCH_COLUMN) as $childId) {
        $childId = (int)$childId;
        $ids[] = $childId;
        $ids = array_merge($ids, descendant_dir_ids($childId));
    }
    return $ids;
}

function grant_dir(int $userId, int $dirId): void
{
    $stmt = db()->prepare('INSERT OR IGNORE INTO directory_permissions(user_id, directory_id) VALUES(?, ?)');
    $stmt->execute([$userId, $dirId]);
}

function grant_all_dirs(int $userId): void
{
    $stmt = db()->prepare('INSERT OR IGNORE INTO directory_permissions(user_id, directory_id) SELECT ?, id FROM directories');
    $stmt->execute([$userId]);
}

function selected_dir_id(array $user): int
{
    $requested = (int)($_GET['dir'] ?? 0);
    if ($requested && can_view_dir($user, $requested)) {
        return $requested;
    }
    if (is_admin($user)) {
        return (int)db()->query('SELECT id FROM directories ORDER BY id LIMIT 1')->fetchColumn();
    }
    $allowed = allowed_dir_ids($user);
    return $allowed[0] ?? 0;
}

function all_dirs(): array
{
    return db()->query('SELECT * FROM directories ORDER BY path')->fetchAll(PDO::FETCH_ASSOC);
}

function dir_path(int $dirId): string
{
    $stmt = db()->prepare('SELECT path FROM directories WHERE id = ?');
    $stmt->execute([$dirId]);
    return (string)$stmt->fetchColumn();
}

function upload_file_to_dir(array $file, int $dirId, int $uploaderId, ?int $proposalId = null): int
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('文件上传失败');
    }
    $original = basename($file['name']);
    $ext = pathinfo($original, PATHINFO_EXTENSION);
    $stored = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
    $target = UPLOAD_DIR . '/' . $stored;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('保存文件失败');
    }
    $mime = mime_content_type($target) ?: 'application/octet-stream';
    $stmt = db()->prepare('
        INSERT INTO files(directory_id, proposal_id, uploader_id, original_name, stored_name, size, mime_type, created_at)
        VALUES(?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$dirId, $proposalId, $uploaderId, $original, $stored, (int)filesize($target), $mime, now()]);
    return (int)db()->lastInsertId();
}

function handle_actions(): void
{
    $action = $_GET['action'] ?? '';
    if ($action === 'captcha') {
        captcha();
    }
    if ($action === 'logout') {
        session_destroy();
        redirect('?page=login');
    }
    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        login_action();
    }
    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        register_action();
    }
    if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        forgot_password_action();
    }
    if ($action === 'file' && isset($_GET['id'])) {
        file_response((int)$_GET['id'], $_GET['mode'] ?? 'download');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $user = require_login();
    try {
        match ($action) {
            'save_user' => save_user($user),
            'delete_user' => delete_user($user),
            'reset_user_password' => reset_user_password($user),
            'approve_user' => approve_user($user),
            'reject_user' => reject_user($user),
            'save_workgroup' => save_workgroup($user),
            'delete_workgroup' => delete_workgroup($user),
            'save_unit' => save_unit($user),
            'delete_unit' => delete_unit($user),
            'save_proposal' => save_proposal($user),
            'copy_proposal' => copy_proposal($user),
            'delete_proposal' => delete_proposal($user),
            'create_dir' => create_dir_action($user),
            'rename_dir' => rename_dir_action($user),
            'delete_dir' => delete_dir_action($user),
            'copy_dir' => copy_dir_action($user),
            'move_dir' => move_dir_action($user),
            'admin_upload' => admin_upload($user),
            'delete_file' => delete_file_action($user),
            'rename_file' => rename_file_action($user),
            'copy_file' => copy_file_action($user),
            'move_file' => move_file_action($user),
            'chief_upload' => chief_upload($user),
            'chief_delete_file' => chief_delete_file($user),
            'chief_rename_file' => chief_rename_file($user),
            'change_password' => change_password($user),
            default => null,
        };
        flash('操作成功');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '?');
}

function captcha(): never
{
    $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $_SESSION['captcha'] = $code;
    if (!function_exists('imagecreate')) {
        header('Content-Type: image/svg+xml; charset=utf-8');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="42"><rect width="120" height="42" fill="#fafafa"/><path d="M6 31 C35 10, 70 35, 114 14" stroke="#d58888" fill="none"/><text x="28" y="27" font-family="Arial" font-size="18" fill="#343a42" letter-spacing="5">' . e($code) . '</text></svg>';
        exit;
    }
    $img = imagecreate(120, 42);
    $bg = imagecolorallocate($img, 250, 250, 250);
    $fg = imagecolorallocate($img, 50, 55, 65);
    $line = imagecolorallocate($img, 210, 120, 120);
    for ($i = 0; $i < 4; $i++) {
        imageline($img, random_int(0, 120), random_int(0, 42), random_int(0, 120), random_int(0, 42), $line);
    }
    imagestring($img, 5, 30, 12, $code, $fg);
    header('Content-Type: image/png');
    imagepng($img);
    imagedestroy($img);
    exit;
}

function login_action(): void
{
    verify_captcha('?page=login');
    $account = trim($_POST['account'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$account, $account]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('账号或密码错误', 'error');
        redirect('?page=login');
    }
    if ($user['status'] === 'pending') {
        flash('账号正在审核中，请等待管理员审核通过后再登录', 'error');
        redirect('?page=login');
    }
    if ($user['status'] !== 'active') {
        flash('账号已被禁用，请联系管理员', 'error');
        redirect('?page=login');
    }
    $_SESSION['user_id'] = (int)$user['id'];
    redirect('?page=files');
}

function verify_captcha(string $redirectUrl): void
{
    $captcha = strtoupper(trim($_POST['captcha'] ?? ''));
    if ($captcha === '' || $captcha !== ($_SESSION['captcha'] ?? '')) {
        flash('验证码错误', 'error');
        redirect($redirectUrl);
    }
}

function validate_password_pair(string $password, string $confirm): void
{
    if (strlen($password) < 6) {
        throw new RuntimeException('密码至少需要 6 位');
    }
    if ($password !== $confirm) {
        throw new RuntimeException('两次输入的密码不一致');
    }
}

function register_action(): void
{
    verify_captcha('?page=register');
    try {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $realName = trim($_POST['real_name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $workgroupId = (int)($_POST['workgroup_id'] ?? 0) ?: null;
        $memberUnitId = (int)($_POST['member_unit_id'] ?? 0) ?: null;
        if ($username === '' || $email === '' || $realName === '') {
            throw new RuntimeException('请完整填写注册信息');
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
            throw new RuntimeException('用户名需为 3-20 位字母、数字或下划线');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('邮箱格式不正确');
        }
        validate_password_pair($password, $confirm);
        $stmt = db()->prepare('
            INSERT INTO users(username, email, real_name, password_hash, role, status, workgroup_id, member_unit_id, created_at)
            VALUES(?, ?, ?, ?, "member", "pending", ?, ?, ?)
        ');
        $stmt->execute([$username, $email, $realName, password_hash($password, PASSWORD_DEFAULT), $workgroupId, $memberUnitId, now()]);
        flash('注册申请已提交，请等待管理员审核');
        redirect('?page=login');
    } catch (PDOException $e) {
        flash('用户名或邮箱已存在', 'error');
        redirect('?page=register');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'error');
        redirect('?page=register');
    }
}

function forgot_password_action(): void
{
    verify_captcha('?page=forgot_password');
    try {
        $account = trim($_POST['account'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $realName = trim($_POST['real_name'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($account === '' || $email === '' || $realName === '') {
            throw new RuntimeException('请完整填写账号验证信息');
        }
        validate_password_pair($password, $confirm);
        $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND email = ? AND real_name = ? AND status = "active"');
        $stmt->execute([$account, $account, $email, $realName]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException('账号信息校验失败，或账号尚未审核通过');
        }
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
        flash('密码已重置，请使用新密码登录');
        redirect('?page=login');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'error');
        redirect('?page=forgot_password');
    }
}

function require_admin(array $user): void
{
    if (!is_admin($user)) {
        throw new RuntimeException('没有权限');
    }
}

function change_password(array $user): void
{
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        throw new RuntimeException('请完整填写密码信息');
    }
    if (!password_verify($currentPassword, $user['password_hash'])) {
        throw new RuntimeException('原密码不正确');
    }
    validate_password_pair($newPassword, $confirmPassword);
    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
}

function save_user(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    $existing = null;
    if ($id > 0) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$existing) {
            throw new RuntimeException('用户不存在');
        }
        if (!can_manage_user($user, $existing)) {
            throw new RuntimeException('无权编辑该用户');
        }
    }
    $data = [
        trim($_POST['username'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['real_name'] ?? ''),
        $_POST['role'] ?? 'member',
        $_POST['status'] ?? 'active',
        (int)($_POST['workgroup_id'] ?? 0) ?: null,
        (int)($_POST['member_unit_id'] ?? 0) ?: null,
    ];
    $editingSuperAdmin = $existing && $existing['role'] === 'super_admin';
    if ($editingSuperAdmin) {
        $data[0] = $existing['username'];
        $data[3] = 'super_admin';
        $data[4] = 'active';
        $data[6] = ensure_alliance_member_unit(db());
    } elseif ($data[3] === 'super_admin') {
        throw new RuntimeException('超管只能在代码里面设置');
    }
    if ($data[0] === '' || $data[1] === '' || $data[2] === '') {
        throw new RuntimeException('请填写用户必填项');
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $data[0])) {
        throw new RuntimeException('用户名需为 3-20 位字母、数字或下划线');
    }
    if (!filter_var($data[1], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('邮箱格式不正确');
    }
    if (!in_array($data[3], ['super_admin', 'admin', 'chief', 'member'], true)) {
        throw new RuntimeException('用户角色不正确');
    }
    if (!can_assign_user_role($user, $data[3])) {
        throw new RuntimeException('无权设置该用户角色');
    }
    if (!in_array($data[4], ['active', 'pending', 'disabled'], true)) {
        throw new RuntimeException('用户状态不正确');
    }
    if ($id > 0) {
        $stmt = db()->prepare('UPDATE users SET username=?, email=?, real_name=?, role=?, status=?, workgroup_id=?, member_unit_id=? WHERE id=?');
        $stmt->execute([...$data, $id]);
    } else {
        $stmt = db()->prepare('INSERT INTO users(username, email, real_name, role, status, workgroup_id, member_unit_id, password_hash, created_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([...$data, password_hash(generate_numeric_password(), PASSWORD_DEFAULT), now()]);
        $id = (int)db()->lastInsertId();
    }
    db()->prepare('DELETE FROM directory_permissions WHERE user_id = ?')->execute([$id]);
    if ($editingSuperAdmin || $data[3] === 'admin') {
        grant_all_dirs($id);
    } else {
        foreach ($_POST['directory_ids'] ?? [] as $dirId) {
            grant_dir($id, (int)$dirId);
        }
    }
}

function delete_user(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    if ($id === (int)$user['id']) {
        throw new RuntimeException('不能删除当前登录账号');
    }
    $target = db()->prepare('SELECT role FROM users WHERE id = ?');
    $target->execute([$id]);
    $targetUser = $target->fetch(PDO::FETCH_ASSOC);
    if (!$targetUser) {
        throw new RuntimeException('用户不存在');
    }
    if (!can_manage_user($user, $targetUser)) {
        throw new RuntimeException('无权删除该用户');
    }
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}

function generate_numeric_password(): string
{
    return (string)random_int(10000000, 99999999);
}

function reset_user_password(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$user['id']) {
        throw new RuntimeException('请通过右上角账号菜单修改当前登录账号密码');
    }
    $stmt = db()->prepare('SELECT id, username, real_name, email, role FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        throw new RuntimeException('用户不存在');
    }
    if ($target['role'] === 'super_admin') {
        throw new RuntimeException('超管密码不允许在用户列表中重置');
    }
    $password = generate_numeric_password();
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    $_SESSION['reset_password_result'] = [
        'password' => $password,
        'username' => $target['username'],
        'real_name' => $target['real_name'],
        'email' => $target['email'],
    ];
}

function approve_user(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('UPDATE users SET status = "active" WHERE id = ? AND status = "pending"')->execute([$id]);
}

function reject_user(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('UPDATE users SET status = "disabled" WHERE id = ? AND status = "pending"')->execute([$id]);
}

function save_workgroup(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($name === '') {
        throw new RuntimeException('工作组名称不能为空');
    }
    if ($id) {
        db()->prepare('UPDATE workgroups SET name=?, description=? WHERE id=?')->execute([$name, $desc, $id]);
    } else {
        db()->prepare('INSERT INTO workgroups(name, description) VALUES(?, ?)')->execute([$name, $desc]);
    }
}

function delete_workgroup(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    foreach (['users' => 'workgroup_id', 'member_units' => 'workgroup_id', 'proposals' => 'workgroup_id'] as $table => $field) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$field} = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('该工作组已被引用，不能删除');
        }
    }
    db()->prepare('DELETE FROM workgroups WHERE id=?')->execute([$id]);
}

function save_unit(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    $workgroup = (int)($_POST['workgroup_id'] ?? 0);
    $company = trim($_POST['company_name'] ?? '');
    $remark = trim($_POST['remark'] ?? '');
    if ($id) {
        $stmt = db()->prepare('SELECT company_name FROM member_units WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() === '星闪联盟') {
            $company = '星闪联盟';
        }
    }
    if (!$workgroup || $company === '') {
        throw new RuntimeException('请填写会员单位必填项');
    }
    if ($id) {
        db()->prepare('UPDATE member_units SET workgroup_id=?, company_name=?, remark=? WHERE id=?')->execute([$workgroup, $company, $remark, $id]);
    } else {
        db()->prepare('INSERT INTO member_units(workgroup_id, company_name, remark) VALUES(?, ?, ?)')->execute([$workgroup, $company, $remark]);
    }
}

function delete_unit(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    if (is_alliance_unit($id)) {
        throw new RuntimeException('星闪联盟是超管默认会员单位，不能删除');
    }
    foreach (['users' => 'member_unit_id', 'proposals' => 'member_unit_id'] as $table => $field) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$field} = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('该会员单位已被引用，不能删除');
        }
    }
    db()->prepare('DELETE FROM member_units WHERE id=?')->execute([$id]);
}

function is_alliance_unit(int $unitId): bool
{
    $stmt = db()->prepare('SELECT company_name FROM member_units WHERE id = ?');
    $stmt->execute([$unitId]);
    return $stmt->fetchColumn() === '星闪联盟';
}

function save_proposal(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    $proposalCode = trim($_POST['proposal_code'] ?? '');
    $dir = (int)($_POST['directory_id'] ?? 0);
    if ($proposalCode === '' || !$dir) {
        throw new RuntimeException('请填写提案号并选择存储目录');
    }
    $fields = [
        $_POST['meeting_date'] ?? date('Y-m-d'),
        trim($_POST['meeting_place'] ?? ''),
        trim($_POST['meeting_subject'] ?? ''),
        (int)($_POST['workgroup_id'] ?? 0),
        (int)($_POST['member_unit_id'] ?? 0),
        (int)($_POST['chief_user_id'] ?? 0),
        trim($_POST['meeting_code'] ?? ''),
        $proposalCode,
        $dir,
        $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
        trim($_POST['description'] ?? ''),
    ];
    if ($id) {
        $stmt = db()->prepare('UPDATE proposals SET meeting_date=?, meeting_place=?, meeting_subject=?, workgroup_id=?, member_unit_id=?, chief_user_id=?, meeting_code=?, proposal_code=?, directory_id=?, due_date=?, description=? WHERE id=?');
        $stmt->execute([...$fields, $id]);
    } else {
        $stmt = db()->prepare('INSERT INTO proposals(meeting_date, meeting_place, meeting_subject, workgroup_id, member_unit_id, chief_user_id, meeting_code, proposal_code, directory_id, due_date, description, created_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([...$fields, now()]);
    }
    grant_dir((int)($_POST['chief_user_id'] ?? 0), $dir);
}

function copy_proposal(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    $stmt = db()->prepare('SELECT * FROM proposals WHERE id=?');
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) {
        throw new RuntimeException('提案不存在');
    }
    $code = $p['proposal_code'] . '_copy_' . date('His') . '_' . random_int(100, 999);
    db()->prepare('INSERT INTO proposals(meeting_date, meeting_place, meeting_subject, workgroup_id, member_unit_id, chief_user_id, meeting_code, proposal_code, directory_id, due_date, description, created_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$p['meeting_date'], $p['meeting_place'], $p['meeting_subject'] . ' (复制)', $p['workgroup_id'], $p['member_unit_id'], $p['chief_user_id'], $p['meeting_code'], $code, $p['directory_id'], $p['due_date'], $p['description'], now()]);
}

function delete_proposal(array $user): void
{
    require_admin($user);
    db()->prepare('DELETE FROM proposals WHERE id=?')->execute([(int)$_POST['id']]);
}

function create_dir_action(array $user): void
{
    require_admin($user);
    $parent = (int)($_POST['parent_id'] ?? 0) ?: null;
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || str_contains($name, '/')) {
        throw new RuntimeException('文件夹名称不合法');
    }
    $parentPath = '';
    if ($parent) {
        $parentPath = dir_path($parent);
    }
    $path = $parentPath ? $parentPath . '/' . $name : $name;
    db()->prepare('INSERT INTO directories(parent_id, name, path, created_at) VALUES(?, ?, ?, ?)')->execute([$parent, $name, $path, now()]);
}

function rename_dir_action(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || str_contains($name, '/')) {
        throw new RuntimeException('文件夹名称不合法');
    }
    $stmt = db()->prepare('SELECT * FROM directories WHERE id=?');
    $stmt->execute([$id]);
    $dir = $stmt->fetch(PDO::FETCH_ASSOC);
    $parentPath = $dir['parent_id'] ? dir_path((int)$dir['parent_id']) : '';
    $oldPath = $dir['path'];
    $newPath = $parentPath ? $parentPath . '/' . $name : $name;
    db()->beginTransaction();
    db()->prepare('UPDATE directories SET name=?, path=? WHERE id=?')->execute([$name, $newPath, $id]);
    $children = db()->prepare('SELECT id, path FROM directories WHERE path LIKE ?');
    $children->execute([$oldPath . '/%']);
    foreach ($children->fetchAll(PDO::FETCH_ASSOC) as $child) {
        $childPath = $newPath . substr($child['path'], strlen($oldPath));
        db()->prepare('UPDATE directories SET path=? WHERE id=?')->execute([$childPath, $child['id']]);
    }
    db()->commit();
}

function delete_dir_action(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    $stmt = db()->prepare('SELECT COUNT(*) FROM files WHERE directory_id IN (' . implode(',', array_fill(0, count(descendant_dir_ids($id)) + 1, '?')) . ')');
    $ids = [$id, ...descendant_dir_ids($id)];
    $stmt->execute($ids);
    if ((int)$stmt->fetchColumn() > 0) {
        throw new RuntimeException('文件夹下已有文件，不能删除');
    }
    db()->prepare('DELETE FROM directories WHERE id=?')->execute([$id]);
}

function copy_dir_action(array $user): void
{
    require_admin($user);
    $sourceId = (int)$_POST['id'];
    $targetParentId = null;
    $targetParentPath = '';
    $target = target_directory_from_post();
    if ($target) {
        $targetParentId = (int)$target['id'];
        $targetParentPath = $target['path'];
    }
    $source = directory_by_id($sourceId);
    if ($target && ((int)$target['id'] === $sourceId || str_starts_with($target['path'], $source['path'] . '/'))) {
        throw new RuntimeException('不能复制到自身或子目录下');
    }
    $newName = $source['name'] . '_copy_' . random_int(100, 999);
    copy_dir_recursive($sourceId, $targetParentId, $targetParentPath, $newName, (int)$user['id']);
}

function move_dir_action(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    $dir = directory_by_id($id);
    $target = target_directory_from_post();
    if (!$target) {
        $targetParentId = null;
        $targetParentPath = '';
    } else {
        if (str_starts_with($target['path'], $dir['path'] . '/') || (int)$target['id'] === $id) {
            throw new RuntimeException('不能移动到自身或子目录下');
        }
        $targetParentId = (int)$target['id'];
        $targetParentPath = $target['path'];
    }
    $oldPath = $dir['path'];
    $newPath = $targetParentPath ? $targetParentPath . '/' . $dir['name'] : $dir['name'];
    db()->beginTransaction();
    db()->prepare('UPDATE directories SET parent_id=?, path=? WHERE id=?')->execute([$targetParentId, $newPath, $id]);
    $children = db()->prepare('SELECT id, path FROM directories WHERE path LIKE ?');
    $children->execute([$oldPath . '/%']);
    foreach ($children->fetchAll(PDO::FETCH_ASSOC) as $child) {
        db()->prepare('UPDATE directories SET path=? WHERE id=?')->execute([$newPath . substr($child['path'], strlen($oldPath)), $child['id']]);
    }
    db()->commit();
}

function target_directory_from_post(): ?array
{
    $targetId = (int)($_POST['directory_id'] ?? 0);
    if ($targetId) {
        return directory_by_id($targetId);
    }
    $targetPath = trim($_POST['target_path'] ?? '');
    if ($targetPath === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT id, path FROM directories WHERE path=?');
    $stmt->execute([$targetPath]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        throw new RuntimeException('目标目录不存在');
    }
    return $target;
}

function directory_by_id(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM directories WHERE id=?');
    $stmt->execute([$id]);
    $dir = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dir) {
        throw new RuntimeException('目录不存在');
    }
    return $dir;
}

function copy_dir_recursive(int $sourceId, ?int $targetParentId, string $targetParentPath, string $newName, int $userId): int
{
    $source = directory_by_id($sourceId);
    $newPath = $targetParentPath ? $targetParentPath . '/' . $newName : $newName;
    db()->prepare('INSERT INTO directories(parent_id, name, path, created_at) VALUES(?, ?, ?, ?)')->execute([$targetParentId, $newName, $newPath, now()]);
    $newId = (int)db()->lastInsertId();
    $files = db()->prepare('SELECT * FROM files WHERE directory_id=?');
    $files->execute([$sourceId]);
    foreach ($files->fetchAll(PDO::FETCH_ASSOC) as $file) {
        $ext = pathinfo($file['stored_name'], PATHINFO_EXTENSION);
        $stored = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
        copy(UPLOAD_DIR . '/' . $file['stored_name'], UPLOAD_DIR . '/' . $stored);
        db()->prepare('INSERT INTO files(directory_id, proposal_id, uploader_id, original_name, stored_name, size, mime_type, created_at) VALUES(?, NULL, ?, ?, ?, ?, ?, ?)')
            ->execute([$newId, $userId, $file['original_name'], $stored, $file['size'], $file['mime_type'], now()]);
    }
    $children = db()->prepare('SELECT * FROM directories WHERE parent_id=? ORDER BY name');
    $children->execute([$sourceId]);
    foreach ($children->fetchAll(PDO::FETCH_ASSOC) as $child) {
        copy_dir_recursive((int)$child['id'], $newId, $newPath, $child['name'], $userId);
    }
    return $newId;
}

function admin_upload(array $user): void
{
    require_admin($user);
    upload_file_to_dir($_FILES['file'] ?? [], (int)$_POST['directory_id'], (int)$user['id']);
}

function file_by_id(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM files WHERE id=?');
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        throw new RuntimeException('文件不存在');
    }
    return $file;
}

function delete_file_action(array $user): void
{
    require_admin($user);
    delete_file_record((int)$_POST['id']);
}

function delete_file_record(int $id): void
{
    $file = file_by_id($id);
    $path = UPLOAD_DIR . '/' . $file['stored_name'];
    if (is_file($path)) {
        unlink($path);
    }
    db()->prepare('DELETE FROM files WHERE id=?')->execute([$id]);
}

function rename_file_action(array $user): void
{
    require_admin($user);
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        throw new RuntimeException('文件名不能为空');
    }
    db()->prepare('UPDATE files SET original_name=? WHERE id=?')->execute([$name, (int)$_POST['id']]);
}

function copy_file_action(array $user): void
{
    require_admin($user);
    $file = file_by_id((int)$_POST['id']);
    $ext = pathinfo($file['stored_name'], PATHINFO_EXTENSION);
    $stored = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . ($ext ? '.' . $ext : '');
    if (!copy(UPLOAD_DIR . '/' . $file['stored_name'], UPLOAD_DIR . '/' . $stored)) {
        throw new RuntimeException('复制文件失败');
    }
    db()->prepare('INSERT INTO files(directory_id, proposal_id, uploader_id, original_name, stored_name, size, mime_type, created_at) VALUES(?, NULL, ?, ?, ?, ?, ?, ?)')
        ->execute([$file['directory_id'], $user['id'], '副本_' . $file['original_name'], $stored, $file['size'], $file['mime_type'], now()]);
}

function move_file_action(array $user): void
{
    require_admin($user);
    $targetId = (int)($_POST['directory_id'] ?? 0);
    if (!$targetId) {
        $targetPath = trim($_POST['target_path'] ?? '');
        $stmt = db()->prepare('SELECT id FROM directories WHERE path=?');
        $stmt->execute([$targetPath]);
        $targetId = (int)$stmt->fetchColumn();
    }
    if (!$targetId) {
        throw new RuntimeException('目标目录不存在');
    }
    db()->prepare('UPDATE files SET directory_id=? WHERE id=?')->execute([$targetId, (int)$_POST['id']]);
}

function chief_upload(array $user): void
{
    if (!is_chief($user)) {
        throw new RuntimeException('没有权限');
    }
    $proposal = proposal_for_chief((int)$_POST['proposal_id'], (int)$user['id']);
    if (is_expired($proposal['due_date'])) {
        throw new RuntimeException('提案已过期，不能上传');
    }
    $fileId = upload_file_to_dir($_FILES['file'] ?? [], (int)$proposal['directory_id'], (int)$user['id'], (int)$proposal['id']);
    db()->prepare('INSERT INTO proposal_uploads(proposal_id, file_id, uploader_id, created_at) VALUES(?, ?, ?, ?)')
        ->execute([$proposal['id'], $fileId, $user['id'], now()]);
    grant_dir((int)$user['id'], (int)$proposal['directory_id']);
}

function chief_delete_file(array $user): void
{
    if (!is_chief($user)) {
        throw new RuntimeException('没有权限');
    }
    $file = file_by_id((int)$_POST['id']);
    $proposal = proposal_for_chief((int)$file['proposal_id'], (int)$user['id']);
    if (is_expired($proposal['due_date'])) {
        throw new RuntimeException('提案已过期，不能删除');
    }
    if ((int)$file['uploader_id'] !== (int)$user['id']) {
        throw new RuntimeException('只能删除自己上传的文件');
    }
    delete_file_record((int)$file['id']);
}

function chief_rename_file(array $user): void
{
    if (!is_chief($user)) {
        throw new RuntimeException('没有权限');
    }
    $file = file_by_id((int)$_POST['id']);
    $proposal = proposal_for_chief((int)$file['proposal_id'], (int)$user['id']);
    if (is_expired($proposal['due_date'])) {
        throw new RuntimeException('提案已过期，不能重命名');
    }
    if ((int)$file['uploader_id'] !== (int)$user['id']) {
        throw new RuntimeException('只能重命名自己上传的文件');
    }
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        throw new RuntimeException('文件名不能为空');
    }
    db()->prepare('UPDATE files SET original_name=? WHERE id=?')->execute([$name, $file['id']]);
}

function proposal_for_chief(int $proposalId, int $chiefId): array
{
    $stmt = db()->prepare('SELECT * FROM proposals WHERE id=? AND chief_user_id=?');
    $stmt->execute([$proposalId, $chiefId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposal) {
        throw new RuntimeException('提案任务不存在');
    }
    return $proposal;
}

function is_expired(string $date): bool
{
    return strtotime($date . ' 23:59:59') < time();
}

function file_response(int $id, string $mode): never
{
    $user = require_login();
    $file = file_by_id($id);
    if (!can_view_dir($user, (int)$file['directory_id'])) {
        http_response_code(403);
        exit('Forbidden');
    }
    $path = UPLOAD_DIR . '/' . $file['stored_name'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    $previewable = str_starts_with($file['mime_type'], 'image/') || $file['mime_type'] === 'application/pdf';
    header('Content-Type: ' . ($previewable && $mode === 'preview' ? $file['mime_type'] : 'application/octet-stream'));
    $disposition = ($previewable && $mode === 'preview') ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($file['original_name']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

handle_actions();
$user = current_user();
$page = $_GET['page'] ?? ($user ? 'files' : 'login');
$publicPages = ['login', 'register', 'forgot_password'];
if (!$user && !in_array($page, $publicPages, true)) {
    redirect('?page=login');
}
if ($user && in_array($page, $publicPages, true)) {
    redirect('?page=files');
}

?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body class="<?= in_array($page, $publicPages, true) ? 'login-body' : ($page === 'files' ? 'files-page' : ($page === 'users' ? 'users-page' : ($page === 'proposals' ? 'proposals-page' : ''))) ?>">
<?php if (in_array($page, $publicPages, true)): ?>
    <?php render_public_page($page); ?>
<?php else: ?>
    <?php render_app($user, $page); ?>
<?php endif; ?>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
<?php

function render_public_page(string $page): void
{
    match ($page) {
        'register' => render_register(),
        'forgot_password' => render_forgot_password(),
        default => render_login(),
    };
}

function render_login(): void
{
    $flash = flash();
    ?>
    <main class="login-page">
        <form class="login-card" method="post" action="?action=login">
            <h1>星闪提案系统登录</h1>
            <div class="login-inner">
                <?php render_flash($flash); ?>
                <label>用户名或邮箱账号：</label>
                <input name="account" value="admin" required>
                <label>密码：</label>
                <input name="password" type="password" value="admin123456" required>
                <label>请输入验证码：</label>
                <div class="captcha-row">
                    <input name="captcha" required autocomplete="off">
                    <img src="?action=captcha&v=<?= time() ?>" onclick="this.src='?action=captcha&v='+Date.now()" alt="captcha">
                </div>
                <button class="primary wide">登录</button>
                <p class="login-links"><a href="?page=register">注册</a><a href="?page=forgot_password">忘记密码</a></p>
            </div>
        </form>
    </main>
    <?php
}

function render_register(): void
{
    $flash = flash();
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $units = db()->query('SELECT * FROM member_units ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <main class="login-page">
        <form class="login-card auth-card" method="post" action="?action=register">
            <h1>会员注册申请</h1>
            <div class="login-inner">
                <?php render_flash($flash); ?>
                <div class="grid2">
                    <label>用户名 *<input name="username" maxlength="20" required></label>
                    <label>邮箱 *<input name="email" type="email" maxlength="50" required></label>
                    <label>姓名 *<input name="real_name" maxlength="20" required></label>
                    <label>工作组 <select name="workgroup_id"><?php options($groups, 'name'); ?></select></label>
                    <label class="wide-field">会员单位 <select name="member_unit_id"><?php options($units, 'company_name'); ?></select></label>
                    <label>密码 *<input name="password" type="password" minlength="6" required></label>
                    <label>确认密码 *<input name="confirm_password" type="password" minlength="6" required></label>
                </div>
                <label>请输入验证码：</label>
                <div class="captcha-row">
                    <input name="captcha" required autocomplete="off">
                    <img src="?action=captcha&v=<?= time() ?>" onclick="this.src='?action=captcha&v='+Date.now()" alt="captcha">
                </div>
                <button class="primary wide">提交注册申请</button>
                <p class="login-links"><a href="?page=login">返回登录</a><a href="?page=forgot_password">忘记密码</a></p>
            </div>
        </form>
    </main>
    <?php
}

function render_forgot_password(): void
{
    $flash = flash();
    ?>
    <main class="login-page">
        <form class="login-card auth-card" method="post" action="?action=forgot_password">
            <h1>重置密码</h1>
            <div class="login-inner">
                <?php render_flash($flash); ?>
                <label>用户名或邮箱账号 *</label>
                <input name="account" required>
                <label>注册邮箱 *</label>
                <input name="email" type="email" required>
                <label>姓名 *</label>
                <input name="real_name" required>
                <div class="grid2">
                    <label>新密码 *<input name="password" type="password" minlength="6" required></label>
                    <label>确认新密码 *<input name="confirm_password" type="password" minlength="6" required></label>
                </div>
                <label>请输入验证码：</label>
                <div class="captcha-row">
                    <input name="captcha" required autocomplete="off">
                    <img src="?action=captcha&v=<?= time() ?>" onclick="this.src='?action=captcha&v='+Date.now()" alt="captcha">
                </div>
                <button class="primary wide">重置密码</button>
                <p class="login-links"><a href="?page=login">返回登录</a><a href="?page=register">注册账号</a></p>
            </div>
        </form>
    </main>
    <?php
}

function render_app(array $user, string $page): void
{
    $allowedPages = ['files'];
    if ($user['role'] !== 'member') {
        $allowedPages[] = 'proposals';
    }
    if (is_admin($user)) {
        $allowedPages[] = 'users';
    }
    if (!in_array($page, $allowedPages, true)) {
        redirect('?page=files');
    }
    ?>
    <header class="topbar">
        <div class="brand">▯ <?= APP_NAME ?></div>
        <div class="top-actions">
            <span>▧ cn 中文⌄</span>
            <div class="account-menu">
                <button type="button" class="account-menu-trigger" onclick="toggleAccountMenu(event)">◉ <?= e($user['email']) ?></button>
                <div class="account-menu-list" id="accountMenu">
                    <button type="button" onclick="openChangePasswordModal()">修改密码</button>
                    <a href="?action=logout">退出</a>
                </div>
            </div>
        </div>
    </header>
    <aside class="sidebar">
        <?= nav_item('files', '▯', '提案文件', $page) ?>
        <?php if (is_admin($user)): ?><?= nav_item('users', '♙', '用户管理', $page) ?><?php endif; ?>
        <?php if ($user['role'] !== 'member'): ?><?= nav_item('proposals', '▤', '提案管理', $page) ?><?php endif; ?>
    </aside>
    <main class="content">
        <?php render_flash(flash()); ?>
        <?php
        match ($page) {
            'files' => render_files($user),
            'users' => render_users($user),
            'proposals' => render_proposals($user),
            default => render_files($user),
        };
        ?>
    </main>
    <div class="modal" id="changePasswordForm">
        <form class="modal-box narrow" method="post" action="?action=change_password">
            <button type="button" class="close" onclick="closeModal('changePasswordForm')">×</button>
            <h3>修改密码</h3>
            <label>原密码 *</label><input name="current_password" id="current_password" type="password" autocomplete="current-password" required>
            <label>新密码 *</label><input name="new_password" type="password" autocomplete="new-password" minlength="6" required>
            <label>确认新密码 *</label><input name="confirm_password" type="password" autocomplete="new-password" minlength="6" required>
            <div class="modal-actions"><button type="button" class="muted" onclick="closeModal('changePasswordForm')">取消</button><button class="primary">保存</button></div>
        </form>
    </div>
    <?php render_reset_password_result_modal(); ?>
    <?php
}

function nav_item(string $page, string $icon, string $label, string $active): string
{
    return '<a class="' . ($page === $active ? 'active' : '') . '" href="?page=' . $page . '">' . $icon . ' ' . e($label) . '</a>';
}

function render_flash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    echo '<div class="flash ' . e($flash['type']) . '">' . e($flash['message']) . '</div>';
}

function render_files(array $user): void
{
    $dirId = selected_dir_id($user);
    $dir = $dirId ? dir_path($dirId) : '';
    $files = [];
    if ($dirId) {
        $stmt = db()->prepare('SELECT f.*, u.real_name AS uploader FROM files f LEFT JOIN users u ON u.id=f.uploader_id WHERE directory_id=? ORDER BY created_at DESC');
        $stmt->execute([$dirId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?>
    <div class="page-title-row files-title-row">
        <h2><?= e($dir ?: '提案文件') ?></h2>
        <?php if (is_admin($user)): ?>
            <div class="folder-actions">
                <button type="button" class="outline folder-actions-trigger" onclick="toggleFolderActions(event)">文件夹操作</button>
                <div class="folder-actions-menu" id="folderActionsMenu">
                    <button type="button" onclick="openFolderActionModal('createDirForm')"><span>□</span> 增加子文件夹</button>
                    <button type="button" onclick="openFolderActionModal('uploadDirForm')"><span>▣</span> 添加文件</button>
                    <button type="button" onclick="openFolderActionModal('renameDirForm')"><span>✎</span> 重命名</button>
                    <button type="button" onclick="openFolderActionModal('copyDirForm')"><span>▣</span> 复制</button>
                    <button type="button" onclick="openFolderActionModal('moveDirForm')"><span>→</span> 移动到</button>
                    <button type="button" onclick="openFolderActionModal('deleteDirForm')" class="menu-danger"><span>▥</span> 删除</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <section class="file-layout">
        <div class="panel tree-panel">
            <div class="tree-panel-header">
                <h3>文件夹结构</h3>
                <div class="tree-tools" aria-label="文件夹结构操作">
                    <button type="button" class="tree-tool-btn" onclick="expandAllDirectories()" title="展开所有文件夹" aria-label="展开所有文件夹">
                        <span class="tree-tool-icon expand-icon" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="tree-tool-btn" onclick="collapseAllDirectories()" title="折叠所有文件夹" aria-label="折叠所有文件夹">
                        <span class="tree-tool-icon collapse-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <div class="tree"><?= render_dir_tree($user, $dirId, 'link') ?></div>
        </div>
        <div class="panel list-panel">
            <table class="file-table">
                <thead><tr><th>文件名</th><th>大小</th><th>上传时间</th><th>上传人</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td class="file-name" title="<?= e($file['original_name']) ?>">▯ <?= e($file['original_name']) ?></td>
                        <td><?= format_size((int)$file['size']) ?></td>
                        <td><?= e($file['created_at']) ?></td>
                        <td><?= e($file['uploader'] ?? '-') ?></td>
                        <td class="actions">
                            <?php if (is_previewable($file)): ?><a class="btn text-action" target="_blank" href="?action=file&mode=preview&id=<?= $file['id'] ?>">预览</a><?php endif; ?>
                            <a class="btn text-action" href="?action=file&mode=download&id=<?= $file['id'] ?>">下载</a>
                            <?php if (is_admin($user)): ?>
                                <button type="button" class="text-action" onclick='openRenameFileModal(<?= json_attr(['id' => (int)$file['id'], 'name' => $file['original_name']]) ?>)'>重命名</button>
                                <form method="post" action="?action=copy_file"><input type="hidden" name="id" value="<?= $file['id'] ?>"><button class="text-action">复制</button></form>
                                <button type="button" class="text-action" onclick='openMoveFileModal(<?= json_attr(['id' => (int)$file['id'], 'name' => $file['original_name'], 'directory_id' => (int)$file['directory_id']]) ?>)'>移动到</button>
                                <form method="post" action="?action=delete_file" onsubmit="return confirm('确定删除文件？')"><input type="hidden" name="id" value="<?= $file['id'] ?>"><button class="text-action danger-link">删除</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$files): ?><tr><td colspan="5" class="empty">暂无文件</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php if (is_admin($user)): ?>
        <div class="modal" id="uploadDirForm">
            <form class="modal-box narrow" method="post" action="?action=admin_upload" enctype="multipart/form-data">
                <button type="button" class="close" onclick="closeModal('uploadDirForm')">×</button>
                <h3>添加文件</h3>
                <input type="hidden" name="directory_id" value="<?= $dirId ?>">
                <label>上传到当前文件夹</label>
                <input type="file" name="file" required>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('uploadDirForm')">取消</button>
                    <button class="primary">上传</button>
                </div>
            </form>
        </div>
        <div class="modal" id="createDirForm">
            <form class="modal-box narrow" method="post" action="?action=create_dir">
                <button type="button" class="close" onclick="closeModal('createDirForm')">×</button>
                <h3>增加子文件夹</h3>
                <input type="hidden" name="parent_id" value="<?= $dirId ?>">
                <label>文件夹名称 *</label>
                <input name="name" placeholder="文件夹名称" required>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('createDirForm')">取消</button>
                    <button class="primary">新增</button>
                </div>
            </form>
        </div>
        <div class="modal" id="renameDirForm">
            <form class="modal-box narrow" method="post" action="?action=rename_dir">
                <button type="button" class="close" onclick="closeModal('renameDirForm')">×</button>
                <h3>重命名文件夹</h3>
                <input type="hidden" name="id" value="<?= $dirId ?>">
                <label>文件夹名称 *</label>
                <input name="name" value="<?= e(basename($dir)) ?>" required>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('renameDirForm')">取消</button>
                    <button class="primary">保存</button>
                </div>
            </form>
        </div>
        <div class="modal" id="copyDirForm">
            <form class="modal-box wide" method="post" action="?action=copy_dir">
                <button type="button" class="close" onclick="closeModal('copyDirForm')">×</button>
                <h3>复制文件夹</h3>
                <input type="hidden" name="id" value="<?= $dirId ?>">
                <label>复制到目标父文件夹</label>
                <div class="tree boxed move-tree"><?= render_dir_tree($user, 0, 'radio') ?></div>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('copyDirForm')">取消</button>
                    <button class="blue">复制</button>
                </div>
            </form>
        </div>
        <div class="modal" id="moveDirForm">
            <form class="modal-box wide" method="post" action="?action=move_dir">
                <button type="button" class="close" onclick="closeModal('moveDirForm')">×</button>
                <h3>移动文件夹</h3>
                <input type="hidden" name="id" value="<?= $dirId ?>">
                <label>移动到目标父文件夹</label>
                <div class="tree boxed move-tree"><?= render_dir_tree($user, 0, 'radio') ?></div>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('moveDirForm')">取消</button>
                    <button class="primary">移动</button>
                </div>
            </form>
        </div>
        <div class="modal" id="deleteDirForm">
            <form class="modal-box narrow" method="post" action="?action=delete_dir">
                <button type="button" class="close" onclick="closeModal('deleteDirForm')">×</button>
                <h3>删除文件夹</h3>
                <input type="hidden" name="id" value="<?= $dirId ?>">
                <p class="confirm-copy">只能删除空文件夹。确定删除当前文件夹吗？</p>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('deleteDirForm')">取消</button>
                    <button class="danger">删除</button>
                </div>
            </form>
        </div>
        <div class="modal" id="renameFileForm">
            <form class="modal-box narrow" method="post" action="?action=rename_file">
                <button type="button" class="close" onclick="closeModal('renameFileForm')">×</button>
                <h3>重命名文件</h3>
                <input type="hidden" name="id" id="rename_file_id">
                <label>文件名 *</label>
                <input name="name" id="rename_file_name" required>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('renameFileForm')">取消</button>
                    <button class="primary">保存</button>
                </div>
            </form>
        </div>
        <div class="modal" id="moveFileForm">
            <form class="modal-box wide" method="post" action="?action=move_file">
                <button type="button" class="close" onclick="closeModal('moveFileForm')">×</button>
                <h3>移动文件</h3>
                <input type="hidden" name="id" id="move_file_id">
                <label>当前文件</label>
                <input id="move_file_name" readonly>
                <label>目标文件夹 *</label>
                <div class="tree boxed move-tree"><?= render_dir_tree($user, 0, 'radio', [], $dirId) ?></div>
                <div class="modal-actions">
                    <button type="button" class="muted" onclick="closeModal('moveFileForm')">取消</button>
                    <button class="primary">移动</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
    <?php
}

function render_dir_tree(array $user, int $activeId = 0, string $mode = 'link', ?array $checked = null, ?int $radio = null): string
{
    $dirs = all_dirs();
    $allIds = array_map(fn($d) => (int)$d['id'], $dirs);
    $allowed = is_admin($user) ? $allIds : allowed_dir_ids($user);
    $visible = is_admin($user) ? $allIds : visible_dir_ids($user);
    $byParent = [];
    foreach ($dirs as $dir) {
        if (!in_array((int)$dir['id'], $visible, true) && !is_admin($user)) {
            continue;
        }
        $byParent[$dir['parent_id'] ?? 0][] = $dir;
    }
    return render_dir_branch($byParent, 0, $activeId, $mode, $checked ?? [], $radio, $allowed);
}

function render_dir_branch(array $byParent, int $parent, int $activeId, string $mode, array $checked, ?int $radio, array $allowed): string
{
    $html = '<ul>';
    foreach ($byParent[$parent] ?? [] as $dir) {
        $id = (int)$dir['id'];
        $isAllowed = in_array($id, $allowed, true);
        $hasChildren = !empty($byParent[$id]);
        $containsActive = $activeId === $id || dir_branch_contains($byParent, $id, $activeId);
        $isOpen = $mode !== 'link' || $containsActive;
        $label = '<span class="tree-folder" aria-hidden="true"></span><span class="tree-name">' . e($dir['name']) . '</span>';
        if ($mode === 'checkbox') {
            $label = '<label><input type="checkbox" name="directory_ids[]" value="' . $id . '" ' . (in_array($id, $checked, true) ? 'checked' : '') . '> ' . $label . '</label>';
        } elseif ($mode === 'radio') {
            $label = '<label><input type="radio" name="directory_id" value="' . $id . '" ' . ($radio === $id ? 'checked' : '') . ' required> ' . $label . '</label>';
        } elseif (!$isAllowed) {
            $label = '<span class="tree-link disabled">' . $label . '</span>';
        } else {
            $label = '<a class="' . ($activeId === $id ? 'active' : '') . '" href="?page=files&dir=' . $id . '">' . $label . '</a>';
        }
        $toggle = $hasChildren
            ? '<button type="button" class="tree-toggle" aria-label="' . ($isOpen ? '折叠' : '展开') . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '"></button>'
            : '<span class="tree-spacer"></span>';
        $html .= '<li class="' . ($isOpen ? 'is-open' : 'is-collapsed') . '" data-dir-id="' . $id . '"><div class="tree-node">' . $toggle . $label . '</div>' . render_dir_branch($byParent, $id, $activeId, $mode, $checked, $radio, $allowed) . '</li>';
    }
    return $html . '</ul>';
}

function dir_branch_contains(array $byParent, int $parent, int $activeId): bool
{
    foreach ($byParent[$parent] ?? [] as $dir) {
        $id = (int)$dir['id'];
        if ($id === $activeId || dir_branch_contains($byParent, $id, $activeId)) {
            return true;
        }
    }
    return false;
}

function format_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    return round($bytes / 1024, 2) . ' KB';
}

function is_previewable(array $file): bool
{
    return str_starts_with($file['mime_type'], 'image/') || $file['mime_type'] === 'application/pdf';
}

function render_users(array $user): void
{
    require_admin($user);
    $tab = $_GET['tab'] ?? 'members';
    if ($tab === 'groups' && ($_GET['sub'] ?? '') === 'units') {
        $tab = 'units';
    }
    ?>
    <nav class="tabs">
        <a class="<?= $tab === 'members' ? 'active' : '' ?>" href="?page=users&tab=members">正式会员</a>
        <a class="<?= $tab === 'pending' ? 'active' : '' ?>" href="?page=users&tab=pending">待审核</a>
        <a class="<?= $tab === 'groups' ? 'active' : '' ?>" href="?page=users&tab=groups">工作组管理</a>
        <a class="<?= $tab === 'units' ? 'active' : '' ?>" href="?page=users&tab=units">会员单位</a>
    </nav>
    <?php
    if ($tab === 'groups') {
        render_workgroups();
    } elseif ($tab === 'units') {
        render_units();
    } elseif ($tab === 'pending') {
        render_pending_users();
        render_user_modal($user);
    } else {
        render_user_table($user);
        render_user_modal($user);
    }
}

function render_user_table(array $currentUser): void
{
    $keyword = trim((string)($_GET['q'] ?? ''));
    $workgroupId = (int)($_GET['workgroup_id'] ?? 0);
    $role = (string)($_GET['role'] ?? '');
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $where = ['u.status != "pending"'];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(u.email LIKE ? OR u.real_name LIKE ? OR u.username LIKE ? OR m.company_name LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($workgroupId > 0) {
        $where[] = 'u.workgroup_id = ?';
        $params[] = $workgroupId;
    }
    if (in_array($role, ['super_admin', 'admin', 'chief', 'member'], true)) {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }
    $stmt = db()->prepare('SELECT u.*, w.name AS workgroup_name, m.company_name FROM users u LEFT JOIN workgroups w ON w.id=u.workgroup_id LEFT JOIN member_units m ON m.id=u.member_unit_id WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC');
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) {
        $stmt = db()->prepare('SELECT directory_id FROM directory_permissions WHERE user_id=?');
        $stmt->execute([(int)$user['id']]);
        $user['permission_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    unset($user);
    ?>
    <form class="toolbar user-filter-toolbar" method="get">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="tab" value="members">
        <input name="q" value="<?= e($keyword) ?>" placeholder="搜索公司、姓名、邮箱、用户名...">
        <select name="workgroup_id">
            <option value="">所有工作组</option>
            <?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" <?= $workgroupId === (int)$group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="role">
            <option value="">所有角色</option>
            <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>超管</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>管理员</option>
            <option value="chief" <?= $role === 'chief' ? 'selected' : '' ?>>首席会员</option>
            <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>普通会员</option>
        </select>
        <button class="primary">查询</button>
        <a class="button outline compact" href="?page=users&tab=members">重置</a>
        <button type="button" class="primary" onclick="newUser()">新增用户</button>
    </form>
    <div class="table-scroll"><table><thead><tr><th>编号</th><th>邮箱</th><th>姓名</th><th>公司名称</th><th>工作组</th><th>角色</th><th>状态</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($users as $u): ?>
        <?php
        $canManage = can_manage_user($currentUser, $u);
        $canResetPassword = $u['role'] !== 'super_admin' && (int)$u['id'] !== (int)($currentUser['id'] ?? 0);
        $canDelete = $canManage && (int)$u['id'] !== (int)($currentUser['id'] ?? 0);
        ?>
        <tr>
            <td><?= $u['id'] ?></td><td><?= e($u['email']) ?></td><td><?= e($u['real_name']) ?></td><td><?= e($u['company_name'] ?? '') ?></td><td><?= e($u['workgroup_name'] ?? '') ?></td><td><?= role_label($u['role']) ?></td><td><?= status_label($u['status']) ?></td>
            <td class="actions">
                <?php if ($canManage): ?><button class="small" onclick='fillUser(<?= json_attr($u) ?>)'>编辑</button><?php endif; ?>
                <?php if ($canResetPassword): ?><form method="post" action="?action=reset_user_password" onsubmit="return confirm('确定为该用户重置密码？')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="small blue">重置密码</button></form><?php endif; ?>
                <?php if ($canDelete): ?><form method="post" action="?action=delete_user" onsubmit="return confirm('确定删除用户？')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="small danger">删除</button></form><?php elseif (in_array($u['role'], ['super_admin', 'admin'], true)): ?><?= $u['role'] === 'super_admin' ? '超管账户' : '管理员账户' ?><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="8" class="empty">暂无用户</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php
}

function render_pending_users(): void
{
    $keyword = trim((string)($_GET['q'] ?? ''));
    $workgroupId = (int)($_GET['workgroup_id'] ?? 0);
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $where = ['u.status = "pending"'];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(u.email LIKE ? OR u.real_name LIKE ? OR u.username LIKE ? OR m.company_name LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($workgroupId > 0) {
        $where[] = 'u.workgroup_id = ?';
        $params[] = $workgroupId;
    }
    $stmt = db()->prepare('SELECT u.*, w.name AS workgroup_name, m.company_name FROM users u LEFT JOIN workgroups w ON w.id=u.workgroup_id LEFT JOIN member_units m ON m.id=u.member_unit_id WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC');
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) {
        $user['permission_ids'] = [];
    }
    unset($user);
    ?>
    <form class="toolbar pending-filter-toolbar" method="get">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="tab" value="pending">
        <input name="q" value="<?= e($keyword) ?>" placeholder="搜索公司、姓名、邮箱、用户名...">
        <select name="workgroup_id">
            <option value="">所有工作组</option>
            <?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" <?= $workgroupId === (int)$group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="primary">查询</button>
        <a class="button outline compact" href="?page=users&tab=pending">重置</a>
    </form>
    <div class="table-scroll"><table><thead><tr><th>编号</th><th>用户名</th><th>邮箱</th><th>姓名</th><th>会员单位</th><th>工作组</th><th>申请时间</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['real_name']) ?></td>
            <td><?= e($u['company_name'] ?? '') ?></td>
            <td><?= e($u['workgroup_name'] ?? '') ?></td>
            <td><?= e($u['created_at']) ?></td>
            <td class="actions">
                <form method="post" action="?action=approve_user"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="small primary">通过</button></form>
                <button class="small" onclick='reviewUser(<?= json_attr($u) ?>)'>编辑</button>
                <form method="post" action="?action=reject_user" onsubmit="return confirm('确定驳回该注册申请？')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="small danger">驳回</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="8" class="empty">暂无待审核申请</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php
}

function role_label(string $role): string
{
    return ['super_admin' => '超管', 'admin' => '管理员', 'chief' => '首席会员', 'member' => '普通会员'][$role] ?? $role;
}

function status_label(string $status): string
{
    return ['active' => '启用', 'pending' => '待审核', 'disabled' => '禁用'][$status] ?? $status;
}

function render_user_modal(array $currentUser): void
{
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $units = db()->query('SELECT * FROM member_units ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="modal" id="userForm">
        <form class="modal-box wide" method="post" action="?action=save_user">
            <button type="button" class="close" onclick="closeModal('userForm')">×</button>
            <h3 id="userFormTitle">编辑用户</h3>
            <input type="hidden" name="id" id="user_id">
            <div class="form-grid two">
                <section class="user-basic-section">
                    <h4>基本信息</h4>
                    <label>用户名 *</label><input name="username" id="user_username" maxlength="20" required>
                    <label>邮箱 *</label><input name="email" id="user_email" maxlength="50" required>
                    <label>姓名 *</label><input name="real_name" id="user_real_name" maxlength="20" required>
                    <label>工作组</label><select name="workgroup_id" id="user_workgroup_id"><?php options($groups, 'name'); ?></select>
                    <label>会员单位</label><select name="member_unit_id" id="user_member_unit_id"><?php options($units, 'company_name'); ?></select>
                    <label>角色 *</label><select name="role" id="user_role"><option value="super_admin" hidden disabled>超管</option><option value="admin" <?= is_super_admin($currentUser) ? '' : 'disabled' ?>>管理员</option><option value="chief">首席会员</option><option value="member">普通会员</option></select>
                    <label>状态 *</label><select name="status" id="user_status"><option value="active">启用</option><option value="disabled">禁用</option></select>
                </section>
                <section>
                    <h4>文件夹权限设置</h4>
                    <div class="tree boxed user-permission-tree"><?= render_dir_tree(['role' => 'admin', 'id' => 0], 0, 'checkbox') ?></div>
                </section>
            </div>
            <div class="modal-actions"><button type="button" class="muted" onclick="closeModal('userForm')">取消</button><button class="primary">保存</button></div>
        </form>
    </div>
    <?php
}

function render_reset_password_result_modal(): void
{
    $result = $_SESSION['reset_password_result'] ?? null;
    unset($_SESSION['reset_password_result']);
    if (!$result) {
        return;
    }
    ?>
    <div class="modal show" id="resetPasswordResult">
        <div class="modal-box narrow">
            <button type="button" class="close" onclick="closeModal('resetPasswordResult')">×</button>
            <h3>密码已重置</h3>
            <p class="confirm-copy">用户：<?= e($result['real_name']) ?>（<?= e($result['username']) ?> / <?= e($result['email']) ?>）</p>
            <label>新密码</label>
            <div class="copy-row">
                <input id="reset_password_value" value="<?= e($result['password']) ?>" readonly>
                <button type="button" class="primary" onclick="copyResetPassword()">复制</button>
            </div>
            <div class="modal-actions"><button type="button" class="primary" onclick="closeModal('resetPasswordResult')">关闭</button></div>
        </div>
    </div>
    <?php
}

function options(array $rows, string $label, ?int $selected = null): void
{
    echo '<option value="">请选择</option>';
    foreach ($rows as $row) {
        echo '<option value="' . (int)$row['id'] . '" ' . ($selected === (int)$row['id'] ? 'selected' : '') . '>' . e($row[$label]) . '</option>';
    }
}

function render_workgroups(): void
{
    $keyword = trim((string)($_GET['q'] ?? ''));
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(name LIKE ? OR description LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like);
    }
    $sql = 'SELECT * FROM workgroups' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <form class="toolbar workgroup-filter-toolbar" method="get">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="tab" value="groups">
        <input name="q" value="<?= e($keyword) ?>" placeholder="搜索工作组名称、描述...">
        <button class="primary">查询</button>
        <a class="button outline compact" href="?page=users&tab=groups">重置</a>
        <button type="button" class="primary" onclick="newWorkgroup()">新增工作组</button>
    </form>
    <div class="table-scroll"><table><thead><tr><th>编号</th><th>工作组名称</th><th>描述</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= $r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td><td class="actions"><button class="small" onclick='fillWorkgroup(<?= json_attr($r) ?>)'>编辑</button><form method="post" action="?action=delete_workgroup" onsubmit="return confirm('确定删除？')"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="small danger">删除</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="modal" id="workgroupForm"><form class="modal-box narrow" method="post" action="?action=save_workgroup"><button type="button" class="close" onclick="closeModal('workgroupForm')">×</button><h3 id="workgroupFormTitle">编辑工作组</h3><input type="hidden" name="id" id="workgroup_id"><label>工作组名称 *</label><input name="name" id="workgroup_name" required><label>描述</label><textarea name="description" id="workgroup_description"></textarea><div class="modal-actions"><button type="button" class="muted" onclick="closeModal('workgroupForm')">取消</button><button class="primary">保存</button></div></form></div>
    <?php
}

function render_units(): void
{
    $keyword = trim((string)($_GET['q'] ?? ''));
    $workgroupId = (int)($_GET['workgroup_id'] ?? 0);
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(m.company_name LIKE ? OR m.remark LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like);
    }
    if ($workgroupId > 0) {
        $where[] = 'm.workgroup_id = ?';
        $params[] = $workgroupId;
    }
    $sql = "SELECT m.*, w.name AS workgroup_name FROM member_units m JOIN workgroups w ON w.id=m.workgroup_id" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY CASE WHEN m.company_name = '星闪联盟' THEN 0 ELSE 1 END, m.id";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <form class="toolbar unit-filter-toolbar" method="get">
        <input type="hidden" name="page" value="users">
        <input type="hidden" name="tab" value="units">
        <input name="q" value="<?= e($keyword) ?>" placeholder="搜索公司名称、备注...">
        <select name="workgroup_id">
            <option value="">所有工作组</option>
            <?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" <?= $workgroupId === (int)$group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="primary">查询</button>
        <a class="button outline compact" href="?page=users&tab=units">重置</a>
        <button type="button" class="primary" onclick="newUnit()">新增会员单位</button>
    </form>
    <div class="table-scroll"><table><thead><tr><th>编号</th><th>工作组</th><th>公司名称</th><th>备注</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr><td><?= $r['id'] ?></td><td><?= e($r['workgroup_name']) ?></td><td><?= e($r['company_name']) ?></td><td><?= e($r['remark']) ?></td><td class="actions"><button class="small" onclick='fillUnit(<?= json_attr($r) ?>)'>编辑</button><?php if ($r['company_name'] !== '星闪联盟'): ?><form method="post" action="?action=delete_unit" onsubmit="return confirm('确定删除？')"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="small danger">删除</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="modal" id="unitForm"><form class="modal-box narrow" method="post" action="?action=save_unit"><button type="button" class="close" onclick="closeModal('unitForm')">×</button><h3 id="unitFormTitle">编辑会员单位</h3><input type="hidden" name="id" id="unit_id"><label>工作组 *</label><select name="workgroup_id" id="unit_workgroup_id"><?php options($groups, 'name'); ?></select><label>公司 *</label><input name="company_name" id="unit_company_name" required><label>备注</label><textarea name="remark" id="unit_remark"></textarea><div class="modal-actions"><button type="button" class="muted" onclick="closeModal('unitForm')">取消</button><button class="primary">保存</button></div></form></div>
    <?php
}

function render_proposals(array $user): void
{
    if (is_admin($user)) {
        render_admin_proposals($user);
    } else {
        render_chief_proposals($user);
    }
}

function proposal_rows(?int $chiefId = null, string $keyword = '', int $workgroupId = 0, string $meetingPlace = ''): array
{
    $sql = 'SELECT p.*, w.name AS workgroup_name, m.company_name, u.real_name AS chief_name, d.path AS dir_path FROM proposals p JOIN workgroups w ON w.id=p.workgroup_id JOIN member_units m ON m.id=p.member_unit_id JOIN users u ON u.id=p.chief_user_id JOIN directories d ON d.id=p.directory_id';
    $where = [];
    $params = [];
    if ($chiefId) {
        $where[] = 'p.chief_user_id = ?';
        $params[] = $chiefId;
    }
    if ($keyword !== '') {
        $where[] = '(p.meeting_subject LIKE ? OR p.meeting_code LIKE ? OR p.proposal_code LIKE ? OR d.path LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if ($workgroupId > 0) {
        $where[] = 'p.workgroup_id = ?';
        $params[] = $workgroupId;
    }
    if ($meetingPlace !== '') {
        $where[] = 'p.meeting_place = ?';
        $params[] = $meetingPlace;
    }
    $stmt = db()->prepare($sql . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.meeting_date DESC');
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function render_admin_proposals(array $user): void
{
    $keyword = trim((string)($_GET['q'] ?? ''));
    $workgroupId = (int)($_GET['workgroup_id'] ?? 0);
    $meetingPlace = trim((string)($_GET['meeting_place'] ?? ''));
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $places = db()->query('SELECT DISTINCT meeting_place FROM proposals WHERE meeting_place != "" ORDER BY meeting_place')->fetchAll(PDO::FETCH_COLUMN);
    $rows = proposal_rows(null, $keyword, $workgroupId, $meetingPlace);
    ?>
    <div class="page-title-row"><h2>提案任务</h2></div>
    <form class="toolbar proposal-filter-toolbar" method="get">
        <input type="hidden" name="page" value="proposals">
        <input name="q" value="<?= e($keyword) ?>" placeholder="搜索会议主题、会议编号、提案号、存储目录...">
        <select name="workgroup_id">
            <option value="">所有工作组</option>
            <?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>" <?= $workgroupId === (int)$group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="meeting_place">
            <option value="">所有会议地点</option>
            <?php foreach ($places as $place): ?><option value="<?= e((string)$place) ?>" <?= $meetingPlace === (string)$place ? 'selected' : '' ?>><?= e((string)$place) ?></option><?php endforeach; ?>
        </select>
        <button class="primary">查询</button>
        <a class="button outline compact" href="?page=proposals">重置</a>
        <button type="button" class="primary" onclick="newProposal()">新建提案</button>
    </form>
    <?php render_proposal_table($rows, $user); render_proposal_modal($user); ?>
    <?php
}

function render_chief_proposals(array $user): void
{
    $rows = proposal_rows((int)$user['id']);
    echo '<div class="page-title-row"><h2>提案任务</h2></div>';
    render_proposal_table($rows, $user);
}

function render_proposal_table(array $rows, array $user): void
{
    ?>
    <div class="table-scroll"><table><thead><tr><th>会议时间</th><th>会议地点</th><th>会议主题</th><th>工作组</th><th>分配的提案号</th><th>存储目录</th><th>有效期</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($rows as $p): $expired = is_expired($p['due_date']); ?>
        <tr>
            <td><?= e(str_replace('-', '/', $p['meeting_date'])) ?></td><td><?= e($p['meeting_place']) ?></td><td><?= e($p['meeting_subject']) ?></td><td><?= e($p['workgroup_name']) ?></td><td><?= e($p['proposal_code']) ?></td><td title="<?= e($p['dir_path']) ?>"><?= e(shorten($p['dir_path'])) ?></td><td class="<?= $expired ? 'date-expired' : 'date-ok' ?>"><?= e(str_replace('-', '/', $p['due_date'])) ?></td>
            <td class="actions">
                <?php if (is_admin($user)): ?>
                    <form method="post" action="?action=copy_proposal"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="small blue">复制</button></form>
                    <button class="small" onclick='fillProposal(<?= json_attr($p) ?>)'>编辑</button>
                    <form method="post" action="?action=delete_proposal" onsubmit="return confirm('确定删除提案？')"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="small danger">删除</button></form>
                <?php else: ?>
                    <?php if (!$expired): ?>
                        <form method="post" action="?action=chief_upload" enctype="multipart/form-data" class="upload-inline"><input type="hidden" name="proposal_id" value="<?= $p['id'] ?>"><input type="file" name="file" required onchange="this.form.submit()"><button type="button" class="small">上传文件</button></form>
                    <?php else: ?><button class="small muted" disabled>已过期</button><?php endif; ?>
                    <?php render_uploads_for_proposal((int)$p['id'], $user, $expired); ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">暂无提案任务</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php
}

function render_uploads_for_proposal(int $proposalId, array $user, bool $expired): void
{
    $stmt = db()->prepare('SELECT * FROM files WHERE proposal_id=? ORDER BY created_at DESC');
    $stmt->execute([$proposalId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
        echo '<span class="upload-chip">▯ ' . e(shorten($file['original_name'], 18)) . '</span>';
        if (!$expired && (int)$file['uploader_id'] === (int)$user['id']) {
            echo '<button class="small ghost" onclick="promptPost(\'?action=chief_rename_file\',{id:' . (int)$file['id'] . '},\'请输入新文件名\',\'name\',' . js($file['original_name']) . ')">改名</button>';
            echo '<form method="post" action="?action=chief_delete_file" onsubmit="return confirm(\'确定删除文件？\')"><input type="hidden" name="id" value="' . (int)$file['id'] . '"><button class="small danger">删</button></form>';
        }
    }
}

function shorten(string $value, int $len = 24): string
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value) > $len ? mb_substr($value, 0, $len) . '...' : $value;
    }
    return strlen($value) > $len ? substr($value, 0, $len) . '...' : $value;
}

function render_proposal_modal(array $user): void
{
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $units = db()->query('SELECT * FROM member_units ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $chiefs = db()->query('SELECT * FROM users WHERE role="chief" AND status="active" ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="modal" id="proposalForm">
        <form class="modal-box xwide proposal-modal-box" method="post" action="?action=save_proposal">
            <button type="button" class="close" onclick="closeModal('proposalForm')">×</button>
            <h3 id="proposalFormTitle">新建提案</h3>
            <input type="hidden" name="id" id="proposal_id">
            <div class="form-grid three proposal-form-grid">
                <section class="proposal-basic-section">
                    <div class="proposal-field-grid">
                        <label>会议主题 *<input name="meeting_subject" id="proposal_meeting_subject" required></label>
                        <label>会议时间 *<input type="date" name="meeting_date" id="proposal_meeting_date" value="<?= date('Y-m-d') ?>" required></label>
                        <label>会议地点 *<input name="meeting_place" id="proposal_meeting_place" required></label>
                        <label>会议编号 *<input name="meeting_code" id="proposal_meeting_code" required></label>
                        <label>会员单位 *<select name="member_unit_id" id="proposal_member_unit_id" required><?php options($units, 'company_name'); ?></select></label>
                        <label>工作组 *<select name="workgroup_id" id="proposal_workgroup_id" required><?php options($groups, 'name'); ?></select></label>
                        <label>首席会员 *<select name="chief_user_id" id="proposal_chief_user_id" required><?php options($chiefs, 'email'); ?></select></label>
                        <label>提案号 *<input name="proposal_code" id="proposal_proposal_code" required></label>
                        <label>有效期 *<input type="date" name="due_date" id="proposal_due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required><small>默认为创建后7天</small></label>
                        <label>描述<textarea name="description" id="proposal_description"></textarea></label>
                    </div>
                </section>
                <section>
                    <h4 class="proposal-dir-title">存储目录 *<small>选择一个文件夹作为提案的存储目录</small></h4>
                    <div class="tree boxed proposal-dir-tree"><?= render_dir_tree($user, 0, 'radio', [], id_by_path('ISLA')) ?></div>
                </section>
            </div>
            <div class="modal-actions"><button type="button" class="muted" onclick="closeModal('proposalForm')">取消</button><button class="primary">保存</button></div>
        </form>
    </div>
    <?php
}
