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
            role TEXT NOT NULL CHECK(role IN ('admin','chief','member')),
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
    $unitStmt = $pdo->prepare('INSERT INTO member_units(workgroup_id, company_name, remark) VALUES(?, ?, ?)');
    foreach ([
        [$wgReq, '中国信息通信研究院', '负责需求分析和标准制定'],
        [$wgSafe, '深圳市宝安区', '安全组会员单位'],
        [$wgHome, '深圳市炎枫科技有限公司', '智能家居会员单位'],
        [$wgReq, '秘书处', '系统管理单位'],
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

    $adminUnit = id_by_name('member_units', 'company_name', '秘书处');
    $safeUnit = id_by_name('member_units', 'company_name', '深圳市宝安区');
    $reqUnit = id_by_name('member_units', 'company_name', '中国信息通信研究院');
    $userStmt = $pdo->prepare('
        INSERT INTO users(username, email, real_name, password_hash, role, status, workgroup_id, member_unit_id, created_at)
        VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $userStmt->execute(['admin', 'admin@example.com', '管理员', password_hash('admin123456', PASSWORD_DEFAULT), 'admin', 'active', $wgReq, $adminUnit, $now]);
    $userStmt->execute(['shouxi2', '234567@qq.com', '测试首席代表', password_hash('chief123456', PASSWORD_DEFAULT), 'chief', 'active', $wgSafe, $safeUnit, $now]);
    $userStmt->execute(['member1', 'member@test.com', '普通会员', password_hash('member123456', PASSWORD_DEFAULT), 'member', 'active', $wgReq, $reqUnit, $now]);

    $root = id_by_path('ISLA');
    $tdoc = id_by_path('ISLA/Tdoc');
    $test = id_by_path('ISLA/test0808');
    $chief = id_by_name('users', 'username', 'shouxi2');
    $member = id_by_name('users', 'username', 'member1');
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
    return $user['role'] === 'admin';
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
    $base = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $all = $base;
    foreach ($base as $dirId) {
        $all = array_merge($all, descendant_dir_ids($dirId));
    }
    $cache[$key] = array_values(array_unique($all));
    return $cache[$key];
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
    $captcha = strtoupper(trim($_POST['captcha'] ?? ''));
    if ($captcha === '' || $captcha !== ($_SESSION['captcha'] ?? '')) {
        flash('验证码错误', 'error');
        redirect('?page=login');
    }
    $account = trim($_POST['account'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND status = "active"');
    $stmt->execute([$account, $account]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('账号或密码错误', 'error');
        redirect('?page=login');
    }
    $_SESSION['user_id'] = (int)$user['id'];
    redirect('?page=' . ($user['role'] === 'member' ? 'files' : 'proposals'));
}

function require_admin(array $user): void
{
    if (!is_admin($user)) {
        throw new RuntimeException('没有权限');
    }
}

function save_user(array $user): void
{
    require_admin($user);
    $id = (int)($_POST['id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');
    $data = [
        trim($_POST['username'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['real_name'] ?? ''),
        $_POST['role'] ?? 'member',
        $_POST['status'] ?? 'active',
        (int)($_POST['workgroup_id'] ?? 0) ?: null,
        (int)($_POST['member_unit_id'] ?? 0) ?: null,
    ];
    if ($data[0] === '' || $data[1] === '' || $data[2] === '') {
        throw new RuntimeException('请填写用户必填项');
    }
    if ($id > 0) {
        if ($password !== '') {
            $stmt = db()->prepare('UPDATE users SET username=?, email=?, real_name=?, role=?, status=?, workgroup_id=?, member_unit_id=?, password_hash=? WHERE id=?');
            $stmt->execute([...$data, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = db()->prepare('UPDATE users SET username=?, email=?, real_name=?, role=?, status=?, workgroup_id=?, member_unit_id=? WHERE id=?');
            $stmt->execute([...$data, $id]);
        }
    } else {
        if ($password === '') {
            throw new RuntimeException('新用户必须设置密码');
        }
        $stmt = db()->prepare('INSERT INTO users(username, email, real_name, role, status, workgroup_id, member_unit_id, password_hash, created_at) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([...$data, password_hash($password, PASSWORD_DEFAULT), now()]);
        $id = (int)db()->lastInsertId();
    }
    db()->prepare('DELETE FROM directory_permissions WHERE user_id = ?')->execute([$id]);
    foreach ($_POST['directory_ids'] ?? [] as $dirId) {
        grant_dir($id, (int)$dirId);
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
    if ($target->fetchColumn() === 'admin') {
        throw new RuntimeException('不能删除管理员账号');
    }
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
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
    foreach (['users' => 'member_unit_id', 'proposals' => 'member_unit_id'] as $table => $field) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$field} = ?");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('该会员单位已被引用，不能删除');
        }
    }
    db()->prepare('DELETE FROM member_units WHERE id=?')->execute([$id]);
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
    $targetPath = trim($_POST['target_path'] ?? '');
    $targetParentId = null;
    $targetParentPath = '';
    if ($targetPath !== '') {
        $stmt = db()->prepare('SELECT id, path FROM directories WHERE path=?');
        $stmt->execute([$targetPath]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            throw new RuntimeException('目标目录不存在');
        }
        $targetParentId = (int)$target['id'];
        $targetParentPath = $target['path'];
    }
    $source = directory_by_id($sourceId);
    $newName = $source['name'] . '_copy_' . random_int(100, 999);
    copy_dir_recursive($sourceId, $targetParentId, $targetParentPath, $newName, (int)$user['id']);
}

function move_dir_action(array $user): void
{
    require_admin($user);
    $id = (int)$_POST['id'];
    $targetPath = trim($_POST['target_path'] ?? '');
    $dir = directory_by_id($id);
    if ($targetPath === '') {
        $targetParentId = null;
        $targetParentPath = '';
    } else {
        $stmt = db()->prepare('SELECT id, path FROM directories WHERE path=?');
        $stmt->execute([$targetPath]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            throw new RuntimeException('目标目录不存在');
        }
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
if (!$user && $page !== 'login') {
    redirect('?page=login');
}
if ($user && $page === 'login') {
    redirect('?page=' . ($user['role'] === 'member' ? 'files' : 'proposals'));
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
<body class="<?= $page === 'login' ? 'login-body' : ($page === 'files' ? 'files-page' : '') ?>">
<?php if ($page === 'login'): ?>
    <?php render_login(); ?>
<?php else: ?>
    <?php render_app($user, $page); ?>
<?php endif; ?>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
<?php

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
                <p class="login-links"><a onclick="alert('请联系管理员创建账号')">注册</a><a onclick="alert('请联系管理员重置密码')">忘记密码</a></p>
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
    if ($user['role'] === 'admin') {
        $allowedPages[] = 'users';
    }
    if (!in_array($page, $allowedPages, true)) {
        redirect('?page=files');
    }
    ?>
    <header class="topbar">
        <div class="brand">▯ <?= APP_NAME ?></div>
        <div class="top-actions">▧ cn 中文⌄ <span>◉ <?= e($user['email']) ?>⌄</span> <a href="?action=logout">退出</a></div>
    </header>
    <aside class="sidebar">
        <?= nav_item('files', '▯', '提案文件', $page) ?>
        <?php if ($user['role'] === 'admin'): ?><?= nav_item('users', '♙', '用户管理', $page) ?><?php endif; ?>
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
            <button class="outline" onclick="openModal('fileOps')">文件夹操作</button>
        <?php endif; ?>
    </div>
    <section class="file-layout">
        <div class="panel tree-panel">
            <h3>文件夹结构</h3>
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
        <div class="modal" id="fileOps">
            <div class="modal-box narrow">
                <button class="close" onclick="closeModal('fileOps')">×</button>
                <h3>文件夹操作</h3>
                <form method="post" action="?action=admin_upload" enctype="multipart/form-data">
                    <label>当前目录上传文件</label>
                    <input type="hidden" name="directory_id" value="<?= $dirId ?>">
                    <input type="file" name="file" required>
                    <button class="primary">上传文件</button>
                </form>
                <hr>
                <form method="post" action="?action=create_dir">
                    <label>增加子文件夹</label>
                    <input type="hidden" name="parent_id" value="<?= $dirId ?>">
                    <input name="name" placeholder="文件夹名称" required>
                    <button class="primary">新增</button>
                </form>
                <hr>
                <form method="post" action="?action=rename_dir">
                    <label>重命名当前文件夹</label>
                    <input type="hidden" name="id" value="<?= $dirId ?>">
                    <input name="name" value="<?= e(basename($dir)) ?>" required>
                    <button class="primary">保存</button>
                </form>
                <form method="post" action="?action=copy_dir">
                    <label>复制当前文件夹到</label>
                    <input type="hidden" name="id" value="<?= $dirId ?>">
                    <input name="target_path" placeholder="目标父目录路径，留空表示复制到根目录">
                    <button class="blue">复制</button>
                </form>
                <form method="post" action="?action=move_dir">
                    <label>移动当前文件夹到</label>
                    <input type="hidden" name="id" value="<?= $dirId ?>">
                    <input name="target_path" placeholder="目标父目录路径，留空表示移动到根目录">
                    <button class="ghost">移动到</button>
                </form>
                <form method="post" action="?action=delete_dir" onsubmit="return confirm('确定删除当前空文件夹？')">
                    <input type="hidden" name="id" value="<?= $dirId ?>">
                    <button class="danger">删除当前空文件夹</button>
                </form>
            </div>
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
    $visible = is_admin($user) ? array_map(fn($d) => (int)$d['id'], $dirs) : allowed_dir_ids($user);
    $byParent = [];
    foreach ($dirs as $dir) {
        if (!in_array((int)$dir['id'], $visible, true) && !is_admin($user)) {
            continue;
        }
        $byParent[$dir['parent_id'] ?? 0][] = $dir;
    }
    return render_dir_branch($byParent, 0, $activeId, $mode, $checked ?? [], $radio);
}

function render_dir_branch(array $byParent, int $parent, int $activeId, string $mode, array $checked, ?int $radio): string
{
    $html = '<ul>';
    foreach ($byParent[$parent] ?? [] as $dir) {
        $id = (int)$dir['id'];
        $hasChildren = !empty($byParent[$id]);
        $containsActive = $activeId === $id || dir_branch_contains($byParent, $id, $activeId);
        $isOpen = $mode !== 'link' || $containsActive;
        $label = '<span class="tree-folder" aria-hidden="true"></span><span class="tree-name">' . e($dir['name']) . '</span>';
        if ($mode === 'checkbox') {
            $label = '<label><input type="checkbox" name="directory_ids[]" value="' . $id . '" ' . (in_array($id, $checked, true) ? 'checked' : '') . '> ' . $label . '</label>';
        } elseif ($mode === 'radio') {
            $label = '<label><input type="radio" name="directory_id" value="' . $id . '" ' . ($radio === $id ? 'checked' : '') . ' required> ' . $label . '</label>';
        } else {
            $label = '<a class="' . ($activeId === $id ? 'active' : '') . '" href="?page=files&dir=' . $id . '">' . $label . '</a>';
        }
        $toggle = $hasChildren
            ? '<button type="button" class="tree-toggle" aria-label="' . ($isOpen ? '折叠' : '展开') . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '"></button>'
            : '<span class="tree-spacer"></span>';
        $html .= '<li class="' . ($isOpen ? 'is-open' : 'is-collapsed') . '" data-dir-id="' . $id . '"><div class="tree-node">' . $toggle . $label . '</div>' . render_dir_branch($byParent, $id, $activeId, $mode, $checked, $radio) . '</li>';
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
    ?>
    <div class="page-title-row"><h2>用户管理</h2><?php if ($tab === 'members'): ?><button class="primary" onclick="newUser()">新增用户</button><?php endif; ?></div>
    <nav class="tabs">
        <a class="<?= $tab === 'members' ? 'active' : '' ?>" href="?page=users&tab=members">正式会员</a>
        <a class="<?= $tab === 'pending' ? 'active' : '' ?>" href="?page=users&tab=pending">待审核</a>
        <a class="<?= $tab === 'groups' ? 'active' : '' ?>" href="?page=users&tab=groups">工作组管理</a>
    </nav>
    <?php
    if ($tab === 'groups') {
        render_group_tabs();
    } elseif ($tab === 'pending') {
        echo '<div class="panel empty-panel">注册审核流程第一版预留，用户由管理员手动新增。</div>';
    } else {
        render_user_table();
        render_user_modal();
    }
}

function render_user_table(): void
{
    $users = db()->query('SELECT u.*, w.name AS workgroup_name, m.company_name FROM users u LEFT JOIN workgroups w ON w.id=u.workgroup_id LEFT JOIN member_units m ON m.id=u.member_unit_id WHERE u.status != "pending" ORDER BY u.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) {
        $stmt = db()->prepare('SELECT directory_id FROM directory_permissions WHERE user_id=?');
        $stmt->execute([(int)$user['id']]);
        $user['permission_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    unset($user);
    ?>
    <div class="toolbar"><input placeholder="搜索公司、姓名、邮箱、用户名..."><select><option>所有工作组</option></select><select><option>所有角色</option></select><button class="outline">重置</button></div>
    <table><thead><tr><th>编号</th><th>邮箱</th><th>姓名</th><th>公司名称</th><th>工作组</th><th>角色</th><th>操作</th></tr></thead><tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= $u['id'] ?></td><td><?= e($u['email']) ?></td><td><?= e($u['real_name']) ?></td><td><?= e($u['company_name'] ?? '') ?></td><td><?= e($u['workgroup_name'] ?? '') ?></td><td><?= role_label($u['role']) ?></td>
            <td class="actions">
                <button class="small" onclick='fillUser(<?= json_attr($u) ?>)'>编辑</button>
                <?php if ($u['role'] !== 'admin'): ?><form method="post" action="?action=delete_user" onsubmit="return confirm('确定删除用户？')"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="small danger">删除</button></form><?php else: ?>管理员账户<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
}

function role_label(string $role): string
{
    return ['admin' => '管理员', 'chief' => '首席会员', 'member' => '普通会员'][$role] ?? $role;
}

function render_user_modal(): void
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
                <section>
                    <h4>基本信息</h4>
                    <label>用户名 *</label><input name="username" id="user_username" maxlength="20" required>
                    <label>邮箱 *</label><input name="email" id="user_email" maxlength="50" required>
                    <label>姓名 *</label><input name="real_name" id="user_real_name" maxlength="20" required>
                    <label>工作组</label><select name="workgroup_id" id="user_workgroup_id"><?php options($groups, 'name'); ?></select>
                    <label>会员单位</label><select name="member_unit_id" id="user_member_unit_id"><?php options($units, 'company_name'); ?></select>
                    <label>角色 *</label><select name="role" id="user_role"><option value="admin">管理员</option><option value="chief">首席会员</option><option value="member">普通会员</option></select>
                    <label>状态 *</label><select name="status" id="user_status"><option value="active">正常</option><option value="disabled">禁用</option></select>
                    <label>密码 *</label><input name="password" type="password"><small>编辑时留空表示不修改密码</small>
                </section>
                <section>
                    <h4>文件夹权限设置</h4>
                    <div class="hint">选择用户可以访问的文件夹；选中父文件夹时自动包含子文件夹。</div>
                    <div class="tree boxed"><?= render_dir_tree(['role' => 'admin', 'id' => 0], 0, 'checkbox') ?></div>
                </section>
            </div>
            <div class="modal-actions"><button type="button" class="muted" onclick="closeModal('userForm')">取消</button><button class="primary">保存</button></div>
        </form>
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

function render_group_tabs(): void
{
    $sub = $_GET['sub'] ?? 'workgroups';
    echo '<nav class="tabs sub"><a class="' . ($sub === 'workgroups' ? 'active' : '') . '" href="?page=users&tab=groups&sub=workgroups">工作组管理</a><a class="' . ($sub === 'units' ? 'active' : '') . '" href="?page=users&tab=groups&sub=units">会员单位</a></nav>';
    $sub === 'units' ? render_units() : render_workgroups();
}

function render_workgroups(): void
{
    $rows = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="panel">
        <form class="inline-form" method="post" action="?action=save_workgroup"><input name="name" placeholder="工作组名称" required><input name="description" placeholder="描述"><button class="primary">添加工作组名称</button></form>
        <table><thead><tr><th>编号</th><th>工作组名称</th><th>描述</th><th>操作</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?><tr><td><?= $r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td><td class="actions"><button class="small" onclick='fillWorkgroup(<?= json_attr($r) ?>)'>编辑</button><form method="post" action="?action=delete_workgroup" onsubmit="return confirm('确定删除？')"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="small danger">删除</button></form></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="modal" id="workgroupForm"><form class="modal-box narrow" method="post" action="?action=save_workgroup"><button type="button" class="close" onclick="closeModal('workgroupForm')">×</button><h3>编辑工作组</h3><input type="hidden" name="id" id="workgroup_id"><label>工作组名称 *</label><input name="name" id="workgroup_name" required><label>描述</label><textarea name="description" id="workgroup_description"></textarea><div class="modal-actions"><button type="button" class="muted" onclick="closeModal('workgroupForm')">取消</button><button class="primary">保存</button></div></form></div>
    <?php
}

function render_units(): void
{
    $rows = db()->query('SELECT m.*, w.name AS workgroup_name FROM member_units m JOIN workgroups w ON w.id=m.workgroup_id ORDER BY m.id')->fetchAll(PDO::FETCH_ASSOC);
    $groups = db()->query('SELECT * FROM workgroups ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="panel">
        <form class="inline-form" method="post" action="?action=save_unit"><select name="workgroup_id" required><?php options($groups, 'name'); ?></select><input name="company_name" placeholder="公司名称" required><input name="remark" placeholder="备注"><button class="primary">添加会员单位</button></form>
        <table><thead><tr><th>编号</th><th>工作组</th><th>公司名称</th><th>备注</th><th>操作</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?><tr><td><?= $r['id'] ?></td><td><?= e($r['workgroup_name']) ?></td><td><?= e($r['company_name']) ?></td><td><?= e($r['remark']) ?></td><td class="actions"><button class="small" onclick='fillUnit(<?= json_attr($r) ?>)'>编辑</button><form method="post" action="?action=delete_unit" onsubmit="return confirm('确定删除？')"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="small danger">删除</button></form></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
    <div class="modal" id="unitForm"><form class="modal-box narrow" method="post" action="?action=save_unit"><button type="button" class="close" onclick="closeModal('unitForm')">×</button><h3>编辑会员单位</h3><input type="hidden" name="id" id="unit_id"><label>工作组 *</label><select name="workgroup_id" id="unit_workgroup_id"><?php options($groups, 'name'); ?></select><label>公司 *</label><input name="company_name" id="unit_company_name" required><label>备注</label><textarea name="remark" id="unit_remark"></textarea><div class="modal-actions"><button type="button" class="muted" onclick="closeModal('unitForm')">取消</button><button class="primary">保存</button></div></form></div>
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

function proposal_rows(?int $chiefId = null): array
{
    $sql = 'SELECT p.*, w.name AS workgroup_name, m.company_name, u.real_name AS chief_name, d.path AS dir_path FROM proposals p JOIN workgroups w ON w.id=p.workgroup_id JOIN member_units m ON m.id=p.member_unit_id JOIN users u ON u.id=p.chief_user_id JOIN directories d ON d.id=p.directory_id';
    if ($chiefId) {
        $stmt = db()->prepare($sql . ' WHERE p.chief_user_id=? ORDER BY p.meeting_date DESC');
        $stmt->execute([$chiefId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return db()->query($sql . ' ORDER BY p.meeting_date DESC')->fetchAll(PDO::FETCH_ASSOC);
}

function render_admin_proposals(array $user): void
{
    $rows = proposal_rows();
    ?>
    <div class="page-title-row"><h2>提案管理</h2><button class="primary" onclick="newProposal()">新建提案</button></div>
    <div class="toolbar"><input placeholder="搜索会议主题、会议编号、存储目录..."><select><option>所有工作组</option></select><select><option>所有会议地点</option></select><button class="outline">重置</button></div>
    <?php render_proposal_table($rows, $user); render_proposal_modal($user); ?>
    <?php
}

function render_chief_proposals(array $user): void
{
    $rows = proposal_rows((int)$user['id']);
    echo '<div class="page-title-row"><h2>提案管理</h2></div>';
    render_proposal_table($rows, $user);
}

function render_proposal_table(array $rows, array $user): void
{
    ?>
    <table><thead><tr><th>会议时间</th><th>会议地点</th><th>会议主题</th><th>工作组</th><th>分配的提案号</th><th>存储目录</th><th>有效期</th><th>操作</th></tr></thead><tbody>
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
    </tbody></table>
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
        <form class="modal-box xwide" method="post" action="?action=save_proposal">
            <button type="button" class="close" onclick="closeModal('proposalForm')">×</button>
            <h3 id="proposalFormTitle">新建提案</h3>
            <input type="hidden" name="id" id="proposal_id">
            <div class="form-grid three">
                <section class="span2">
                    <div class="grid2">
                        <label>会议时间 *<input type="date" name="meeting_date" id="proposal_meeting_date" value="<?= date('Y-m-d') ?>" required></label>
                        <label>会议地点 *<input name="meeting_place" id="proposal_meeting_place" required></label>
                        <label class="wide-field">会议主题 *<input name="meeting_subject" id="proposal_meeting_subject" required></label>
                        <label>工作组 *<select name="workgroup_id" id="proposal_workgroup_id" required><?php options($groups, 'name'); ?></select></label>
                        <label>会员单位 *<select name="member_unit_id" id="proposal_member_unit_id" required><?php options($units, 'company_name'); ?></select></label>
                        <label class="wide-field">首席会员 *<select name="chief_user_id" id="proposal_chief_user_id" required><?php options($chiefs, 'email'); ?></select></label>
                        <label>会议编号 *<input name="meeting_code" id="proposal_meeting_code" required></label>
                        <label>分配的提案号 *<input name="proposal_code" id="proposal_proposal_code" required></label>
                        <label>有效期 *<input type="date" name="due_date" id="proposal_due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required><small>默认为创建后7天</small></label>
                        <label>描述<textarea name="description" id="proposal_description"></textarea></label>
                    </div>
                </section>
                <section>
                    <h4>存储目录 *</h4>
                    <div class="tree boxed"><?= render_dir_tree($user, 0, 'radio', [], id_by_path('ISLA')) ?></div>
                    <small>选择一个文件夹作为提案的存储目录</small>
                </section>
            </div>
            <div class="modal-actions"><button type="button" class="muted" onclick="closeModal('proposalForm')">取消</button><button class="primary">保存</button></div>
        </form>
    </div>
    <?php
}
