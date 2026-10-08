<?php
session_start();
date_default_timezone_set('Africa/Cairo');

define('DB_HOST', 'sql303.infinityfree.com');
define('DB_USER', 'if0_43023184');
define('DB_PASS', '2IP2d1CBQ3VSRb');
define('DB_NAME', 'if0_43023184_hanish_db');
define('ADMIN_PASSWORD', 'Gis.2030');

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("فشل الاتصال: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fetchOne($sql, $p = []) {
    global $conn;
    $s = $conn->prepare($sql);
    if (!$s) return null;
    if ($p) { $s->bind_param(str_repeat('s', count($p)), ...$p); }
    $s->execute();
    return $s->get_result()->fetch_assoc();
}
function fetchAll($sql, $p = []) {
    global $conn;
    $s = $conn->prepare($sql);
    if (!$s) return [];
    if ($p) { $s->bind_param(str_repeat('s', count($p)), ...$p); }
    $s->execute();
    $r = $s->get_result();
    $out = [];
    while ($row = $r->fetch_assoc()) $out[] = $row;
    return $out;
}

// إنشاء الجداول
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),
    role ENUM('student','parent','admin') DEFAULT 'student',
    grade VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// إنشاء الأدمن
$admin = fetchOne("SELECT id FROM users WHERE email='admin@haneesh.com'");
if (!$admin) {
    $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $s = $conn->prepare("INSERT INTO users (full_name,email,password,role) VALUES (?,?,?,'admin')");
    $n='مدير المنصة'; $e='admin@haneesh.com';
    $s->bind_param('sss', $n, $e, $hash);
    $s->execute();
}

$page = $_GET['page'] ?? 'home';
$msg = '';

// تسجيل
if (($_POST['action'] ?? '') === 'register') {
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $s = $conn->prepare("INSERT INTO users (full_name,email,password,role,grade) VALUES (?,?,?,?,?)");
    $r = $_POST['role'] ?? 'student';
    $g = $_POST['grade'] ?? '';
    $s->bind_param('sssss', $_POST['full_name'], $_POST['email'], $hash, $r, $g);
    if ($s->execute()) {
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['name'] = $_POST['full_name'];
        header('Location: ?page=dashboard'); exit;
    } else $msg = 'البريد مسجل مسبقاً';
}

// دخول
if (($_POST['action'] ?? '') === 'login') {
    $u = fetchOne("SELECT * FROM users WHERE email=?", [$_POST['email']]);
    if ($u && password_verify($_POST['password'], $u['password'])) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['name'] = $u['full_name'];
        $_SESSION['role'] = $u['role'];
        header('Location: ?page=dashboard'); exit;
    } else $msg = 'بيانات غير صحيحة';
}

// دخول أدمن
if (($_POST['action'] ?? '') === 'admin_login') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: ?page=admin'); exit;
    } else $msg = 'كلمة السر غلط';
}

// خروج
if ($page === 'logout') { session_destroy(); header('Location: ?'); exit; }

$user = isset($_SESSION['user_id']) ? fetchOne("SELECT * FROM users WHERE id=?", [$_SESSION['user_id']]) : null;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>منصة عائلة حنيش</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
<style>
* { font-family: 'Cairo', sans-serif; }
.g-hero { background: linear-gradient(135deg, #065f46, #047857, #0f766e); }
.g-primary { background: linear-gradient(135deg, #059669, #0d9488); }
.fade { animation: fadeIn 0.6s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body class="bg-gray-50">

<!-- Navbar -->
<nav class="g-hero text-white shadow-lg">
    <div class="max-w-6xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="?" class="flex items-center gap-2 font-black text-lg">
            <span class="text-2xl">📚</span> عائلة حنيش
        </a>
        <div class="flex gap-4 text-sm">
            <a href="?">الرئيسية</a>
            <?php if ($user): ?>
                <span>👤 <?= e($user['full_name']) ?></span>
                <a href="?page=logout" class="bg-red-500/80 px-3 py-1 rounded-lg">خروج</a>
            <?php elseif (!empty($_SESSION['admin'])): ?>
                <a href="?page=admin">👑 الأدمن</a>
                <a href="?page=logout" class="bg-red-500/80 px-3 py-1 rounded-lg">خروج</a>
            <?php else: ?>
                <a href="?page=login" class="bg-white/20 px-3 py-1 rounded-lg">دخول</a>
                <a href="?page=register" class="bg-white text-emerald-800 px-3 py-1 rounded-lg font-bold">حساب جديد</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($msg): ?>
<div class="bg-red-500 text-white text-center py-3 font-bold"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($page === 'home'): ?>
<section class="g-hero text-white py-20 fade">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-6xl font-black mb-4">منصة عائلة حنيش التعليمية</h1>
        <p class="text-xl mb-8">تعلّم مع الذكاء الاصطناعي من الصف الأول حتى الثالث الثانوي</p>
        <a href="?page=register" class="bg-white text-emerald-800 px-8 py-4 rounded-xl font-bold text-lg shadow-lg inline-block">🚀 ابدأ الآن</a>
    </div>
</section>
<div class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow text-center fade">
            <div class="text-5xl mb-3">🤖</div>
            <h3 class="font-bold text-xl">مدرّس ذكي</h3>
            <p class="text-gray-500 text-sm mt-2">اسأل في أي وقت</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow text-center fade">
            <div class="text-5xl mb-3">📚</div>
            <h3 class="font-bold text-xl">جميع المراحل</h3>
            <p class="text-gray-500 text-sm mt-2">من الابتدائي للثانوي</p>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow text-center fade">
            <div class="text-5xl mb-3">👨‍👩‍👧</div>
            <h3 class="font-bold text-xl">متابعة الأبناء</h3>
            <p class="text-gray-500 text-sm mt-2">لأولياء الأمور</p>
        </div>
    </div>
</div>

<?php elseif ($page === 'register'): ?>
<div class="g-hero min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md fade">
        <h1 class="text-2xl font-black text-center mb-6">📝 إنشاء حساب</h1>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="register">
            <input type="text" name="full_name" placeholder="الاسم الكامل" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none">
            <input type="email" name="email" placeholder="البريد الإلكتروني" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none">
            <input type="password" name="password" placeholder="كلمة السر" required minlength="6" class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none">
            <select name="role" class="w-full px-4 py-3 border-2 rounded-xl">
                <option value="student">👨‍🎓 طالب</option>
                <option value="parent">👨‍👩‍👧 ولي أمر</option>
            </select>
            <select name="grade" class="w-full px-4 py-3 border-2 rounded-xl">
                <?php $grades=['الصف الأول الابتدائي','الصف الثاني الابتدائي','الصف الثالث الابتدائي','الصف الرابع الابتدائي','الصف الخامس الابتدائي','الصف السادس الابتدائي','الصف الأول الإعدادي','الصف الثاني الإعدادي','الصف الثالث الإعدادي','الصف الأول الثانوي','الصف الثاني الثانوي','الصف الثالث الثانوي'];
                foreach ($grades as $g) echo "<option>$g</option>"; ?>
            </select>
            <button class="w-full g-primary text-white py-3 rounded-xl font-bold">إنشاء الحساب</button>
        </form>
        <p class="text-center mt-4 text-gray-500">لديك حساب؟ <a href="?page=login" class="text-emerald-600 font-bold">دخول</a></p>
    </div>
</div>

<?php elseif ($page === 'login'): ?>
<div class="g-hero min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md fade">
        <h1 class="text-2xl font-black text-center mb-6">🔐 تسجيل الدخول</h1>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <input type="email" name="email" placeholder="البريد الإلكتروني" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none">
            <input type="password" name="password" placeholder="كلمة السر" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none">
            <button class="w-full g-primary text-white py-3 rounded-xl font-bold">دخول</button>
        </form>
        <p class="text-center mt-4 text-gray-500">ليس لديك حساب؟ <a href="?page=register" class="text-emerald-600 font-bold">أنشئ حساب</a></p>
        <p class="text-center mt-3 text-sm"><a href="?page=admin_login" class="text-gray-400">🔑 دخول الأدمن</a></p>
    </div>
</div>

<?php elseif ($page === 'admin_login'): ?>
<div class="g-hero min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md fade">
        <h1 class="text-2xl font-black text-center mb-6">👑 دخول الأدمن</h1>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="admin_login">
            <input type="password" name="password" placeholder="كلمة سر الأدمن" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-yellow-500 outline-none">
            <button class="w-full bg-yellow-500 text-white py-3 rounded-xl font-bold">دخول 👑</button>
        </form>
    </div>
</div>

<?php elseif ($page === 'dashboard' && $user): ?>
<div class="max-w-4xl mx-auto px-4 py-10 fade">
    <div class="g-hero text-white rounded-2xl p-8 mb-6">
        <h1 class="text-3xl font-black mb-2">أهلاً <?= e($user['full_name']) ?> 👋</h1>
        <p><?= e($user['grade'] ?: 'ولي أمر') ?></p>
    </div>
    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow text-center">
            <div class="text-5xl mb-3">🤖</div>
            <h3 class="font-bold text-xl mb-2">المدرّس الذكي</h3>
            <p class="text-gray-500 text-sm mb-4">اسأل أي سؤال</p>
            <button class="g-primary text-white px-6 py-2 rounded-lg font-bold">قريباً</button>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow text-center">
            <div class="text-5xl mb-3">📚</div>
            <h3 class="font-bold text-xl mb-2">موادي</h3>
            <p class="text-gray-500 text-sm mb-4">دروس صفك</p>
            <button class="g-primary text-white px-6 py-2 rounded-lg font-bold">قريباً</button>
        </div>
    </div>
</div>

<?php elseif ($page === 'admin' && !empty($_SESSION['admin'])): 
    $stats = [
        'طلاب' => fetchOne("SELECT COUNT(*) c FROM users WHERE role='student'")['c'],
        'أولياء أمور' => fetchOne("SELECT COUNT(*) c FROM users WHERE role='parent'")['c'],
        'إجمالي' => fetchOne("SELECT COUNT(*) c FROM users")['c']
    ];
?>
<div class="max-w-4xl mx-auto px-4 py-10 fade">
    <div class="bg-yellow-500 text-white rounded-2xl p-6 mb-6">
        <h1 class="text-2xl font-black">👑 لوحة الأدمن</h1>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
        <?php foreach ($stats as $k=>$v): ?>
        <div class="bg-white p-6 rounded-2xl shadow text-center">
            <div class="text-3xl font-black text-emerald-600"><?= $v ?></div>
            <div class="text-gray-500 mt-2"><?= $k ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

<footer class="bg-gray-900 text-white text-center py-6 mt-12">
    <p>© <?= date('Y') ?> منصة عائلة حنيش التعليمية</p>
</footer>

</body>
</html>
