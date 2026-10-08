<?php
session_start();
date_default_timezone_set('Africa/Cairo');
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('DB_HOST', 'sql303.infinityfree.com');
define('DB_USER', 'if0_43023184');
define('DB_PASS', '2IP2d1CBQ3VSRb');
define('DB_NAME', 'if0_43023184_hanish_db');
define('SITE_NAME', 'منصة عائلة حنيش التعليمية');
define('ADMIN_PASSWORD', 'Gis.2030');
define('AI_API_KEY', '');

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("<div style='font-family:Cairo;padding:40px;text-align:center'><h2 style='color:red'>فشل الاتصال بقاعدة البيانات</h2><p>" . htmlspecialchars($conn->connect_error) . "</p></div>");
$conn->set_charset("utf8mb4");

/* ============ دوال ============ */
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function go($u) { header("Location: $u"); exit; }

function q($sql, $p = []) {
    global $conn;
    $s = $conn->prepare($sql);
    if (!$s) return false;
    if (!empty($p)) {
        $types = '';
        foreach ($p as $x) { $types .= is_int($x) ? 'i' : (is_float($x) ? 'd' : 's'); }
        $s->bind_param($types, ...$p);
    }
    $s->execute();
    return $s;
}
function fetchOne($sql, $p = []) {
    $s = q($sql, $p);
    if (!$s) return null;
    $r = $s->get_result();
    return $r ? $r->fetch_assoc() : null;
}
function fetchAll($sql, $p = []) {
    $s = q($sql, $p);
    if (!$s) return [];
    $r = $s->get_result();
    $out = [];
    if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
    return $out;
}
function exe($sql, $p = []) {
    $s = q($sql, $p);
    if ($s) { $s->close(); return true; }
    return false;
}
function currentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    return fetchOne("SELECT * FROM users WHERE id=?", [(int)$_SESSION['user_id']]);
}
function isAdmin() { return !empty($_SESSION['admin']); }
function isLogged() { return isset($_SESSION['user_id']); }
function csrf() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function ckCsrf($t) { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t ?? ''); }
function flash() {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function flashSet($type, $msg) { $_SESSION['flash'] = [$type, $msg]; }

/* ============ محرك AI ============ */
function generateAI($question, $context = '') {
    if (AI_API_KEY) {
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role'=>'system','content'=>"أنت مدرّس ذكي لمنصة عائلة حنيش التعليمية. {$context}اشرح بالعربية بوضوح وبساطة."],
                ['role'=>'user','content'=>$question]
            ],
            'max_tokens' => 700,
        ];
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json','Authorization: Bearer '.AI_API_KEY],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        if ($resp) {
            $j = json_decode($resp, true);
            if (isset($j['choices'][0]['message']['content'])) return trim($j['choices'][0]['message']['content']);
        }
    }
    
    $q = mb_strtolower($question);
    $rules = [
        ['رياضيات|معادلة|جمع|طرح|ضرب|قسمة|هندسة|تفاضل|تكامل|جبر|معادله', "📐 **سؤال رياضياتي**\n\nسأساعدك خطوة بخطوة:\n\n1️⃣ حدد المعطيات\n2️⃣ اكتب القانون المناسب\n3️⃣ طبّق الحل بالترتيب\n4️⃣ تحقق من الناتج\n\n✍️ اكتب المسألة كاملة."],
        ['عربي|نحو|إعراب|قواعد|شعر|نص|بلاغة', "📖 **سؤال لغة عربية**\n\nللإعراب:\n• الفاعل: مرفوع\n• المفعول به: منصوب\n• المبتدأ والخبر: مرفوعان\n\n✍️ أرسل الجملة كاملة."],
        ['إنجليزي|english|grammar|verb|tense|translation', "🔤 **English Question**\n\nI'll help you with:\n• Tenses (Present, Past, Future)\n• Vocabulary & Idioms\n• Grammar rules\n• Translation AR↔EN\n\n✍️ Send me your question! 🌟"],
        ['علوم|فيزياء|كيمياء|أحياء|خلية|ذرة|طاقة|تفاعل', "🔬 **سؤال علمي**\n\nسأشرح المفهوم بطريقة مبسطة:\n• التعريف الأساسي\n• أمثلة من الحياة\n• تطبيق عملي\n\n✍️ اسأل عن أي مفهوم."],
        ['امتحان|اختبار|مراجعة|مذاكرة|حفظ', "📝 **نصائح للمذاكرة**\n\n1️⃣ قسّم المنهج لجزئيات\n2️⃣ تقنية بومودورو: 25 دقيقة + 5 راحة\n3️⃣ لخّص كل درس في صفحة\n4️⃣ حل أسئلة سابقة\n5️⃣ راجع قبل النوم\n\n💪 بالتوفيق!"],
        ['مرحبا|السلام|أهلا|هاي|hi|hello|صباح|مساء', "أهلاً وسهلاً! 👋\n\nأنا مدرّسك الذكي. اسألني عن:\n📚 أي مادة دراسية\n🧮 حل المسائل\n📖 شرح المفاهيم\n💡 نصائح للمذاكرة\n\n✍️ اسألني أي حاجة! 🤖"],
    ];
    foreach ($rules as $r) {
        if (preg_match('/'.$r[0].'/u', $q)) return $r[1];
    }
    return "🌟 شكراً على سؤالك!\n\n📝 **{$question}**\n\nللحصول على رد ذكي:\n• أعد الصياغة بوضوح\n• أخبرني بالصف والمادة\n• اكتب المعطيات كاملة\n\n💡 لتفعيل GPT، أضف مفتاح OpenAI في الإعدادات.\n\nأنا هنا دائماً 🤖";
}

/* ============ إنشاء الجداول ============ */
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','parent','admin') DEFAULT 'student',
    grade VARCHAR(50) DEFAULT NULL,
    parent_email VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade VARCHAR(50) NOT NULL,
    icon VARCHAR(50) DEFAULT '📚',
    color VARCHAR(20) DEFAULT '#059669',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    video_url VARCHAR(500),
    duration INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq (user_id, lesson_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS ai_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('user','assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ============ إنشاء الأدمن ============ */
if (!fetchOne("SELECT id FROM users WHERE email='admin@haneesh.com'")) {
    $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
    exe("INSERT INTO users (full_name,email,password,role) VALUES ('مدير المنصة','admin@haneesh.com',?,'admin')", [$hash]);
}

/* ============ مواد تجريبية ============ */
if (fetchOne("SELECT COUNT(*) c FROM subjects")['c'] == 0) {
    $grades = ['الصف الأول الابتدائي','الصف الثاني الابتدائي','الصف الثالث الابتدائي',
               'الصف الرابع الابتدائي','الصف الخامس الابتدائي','الصف السادس الابتدائي',
               'الصف الأول الإعدادي','الصف الثاني الإعدادي','الصف الثالث الإعدادي',
               'الصف الأول الثانوي','الصف الثاني الثانوي','الصف الثالث الثانوي'];
    $subs = [
        ['اللغة العربية','📖','#059669','أساسيات القراءة والكتابة والنحو'],
        ['الرياضيات','🔢','#3b82f6','الأرقام والعمليات الحسابية'],
        ['اللغة الإنجليزية','🔤','#8b5cf6','الحروف والكلمات والقواعد'],
        ['العلوم','🔬','#f59e0b','مقدمة في العلوم الطبيعية'],
    ];
    foreach ($grades as $g) {
        foreach ($subs as $s) {
            exe("INSERT INTO subjects (name,grade,icon,color,description) VALUES (?,?,?,?,?)", [$s[0],$g,$s[1],$s[2],$s[3]]);
        }
    }
}

$gradesList = ['الصف الأول الابتدائي','الصف الثاني الابتدائي','الصف الثالث الابتدائي',
               'الصف الرابع الابتدائي','الصف الخامس الابتدائي','الصف السادس الابتدائي',
               'الصف الأول الإعدادي','الصف الثاني الإعدادي','الصف الثالث الإعدادي',
               'الصف الأول الثانوي','الصف الثاني الثانوي','الصف الثالث الثانوي'];

$page = $_GET['page'] ?? 'home';
$action = $_POST['action'] ?? '';

/* ============ الإجراءات ============ */
if ($page === 'logout') { session_destroy(); go('?page=home'); }

/* تسجيل */
if ($action === 'register' && ckCsrf($_POST['csrf'] ?? '')) {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $grade = $_POST['grade'] ?? null;
    $pe = trim($_POST['parent_email'] ?? '');
    if (strlen($pass) < 6) flashSet('error','كلمة السر 6 أحرف على الأقل');
    elseif (fetchOne("SELECT id FROM users WHERE email=?", [$email])) flashSet('error','البريد مسجل مسبقاً');
    else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        exe("INSERT INTO users (full_name,email,password,role,grade,parent_email) VALUES (?,?,?,?,?,?)",
            [$name,$email,$hash,$role,$grade,$pe ?: null]);
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = $role;
        flashSet('success','تم إنشاء حسابك بنجاح');
        go($role === 'parent' ? '?page=parent' : '?page=dashboard');
    }
    go('?page=register');
}

/* دخول */
if ($action === 'login' && ckCsrf($_POST['csrf'] ?? '')) {
    $u = fetchOne("SELECT * FROM users WHERE email=?", [trim($_POST['email'] ?? '')]);
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['name'] = $u['full_name'];
        $_SESSION['role'] = $u['role'];
        go($u['role'] === 'parent' ? '?page=parent' : '?page=dashboard');
    }
    flashSet('error','بيانات الدخول غير صحيحة');
    go('?page=login');
}

/* دخول أدمن */
if ($action === 'admin_login' && ckCsrf($_POST['csrf'] ?? '')) {
    if (($_POST['password'] ?? '') === ADMIN_PASSWORD) { $_SESSION['admin'] = true; go('?page=admin'); }
    flashSet('error','كلمة السر غير صحيحة');
    go('?page=admin_login');
}

/* أدمن: إضافة مادة */
if ($action === 'add_subject' && isAdmin() && ckCsrf($_POST['csrf'] ?? '')) {
    exe("INSERT INTO subjects (name,grade,icon,color,description) VALUES (?,?,?,?,?)",
        [$_POST['name'], $_POST['grade'], $_POST['icon'] ?: '📚', $_POST['color'] ?: '#059669', $_POST['description'] ?? '']);
    flashSet('success','تمت إضافة المادة');
    go('?page=admin&tab=subjects');
}

/* أدمن: إضافة درس */
if ($action === 'add_lesson' && isAdmin() && ckCsrf($_POST['csrf'] ?? '')) {
    exe("INSERT INTO lessons (subject_id,title,description,video_url,duration) VALUES (?,?,?,?,?)",
        [(int)$_POST['subject_id'], $_POST['title'], $_POST['description'] ?? '', $_POST['video_url'] ?? '', (int)($_POST['duration'] ?? 0)]);
    flashSet('success','تمت إضافة الدرس');
    go('?page=admin&tab=lessons');
}

/* أدمن: حذف */
if ($page === 'del_subject' && isAdmin() && isset($_GET['id'])) {
    exe("DELETE FROM subjects WHERE id=?", [(int)$_GET['id']]);
    flashSet('success','تم الحذف'); go('?page=admin&tab=subjects');
}
if ($page === 'del_lesson' && isAdmin() && isset($_GET['id'])) {
    exe("DELETE FROM lessons WHERE id=?", [(int)$_GET['id']]);
    flashSet('success','تم الحذف'); go('?page=admin&tab=lessons');
}
if ($page === 'del_user' && isAdmin() && isset($_GET['id'])) {
    exe("DELETE FROM users WHERE id=? AND role!='admin'", [(int)$_GET['id']]);
    flashSet('success','تم الحذف'); go('?page=admin&tab=users');
}

/* إكمال درس */
if ($action === 'complete_lesson' && isLogged() && ckCsrf($_POST['csrf'] ?? '')) {
    exe("INSERT INTO progress (user_id,lesson_id,completed) VALUES (?,?,1) ON DUPLICATE KEY UPDATE completed=1",
        [(int)$_SESSION['user_id'], (int)$_POST['lesson_id']]);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]); exit;
}

/* شات AI */
if ($action === 'chat' && isLogged() && ckCsrf($_POST['csrf'] ?? '')) {
    $msg = trim($_POST['message'] ?? '');
    header('Content-Type: application/json');
    if ($msg === '') { echo json_encode(['ok'=>false]); exit; }
    exe("INSERT INTO ai_messages (user_id,role,message) VALUES (?,?,?)", [$_SESSION['user_id'],'user',$msg]);
    $u = currentUser();
    $ctx = $u && $u['grade'] ? "الطالب في {$u['grade']}. " : "";
    $reply = generateAI($msg, $ctx);
    exe("INSERT INTO ai_messages (user_id,role,message) VALUES (?,?,?)", [$_SESSION['user_id'],'assistant',$reply]);
    echo json_encode(['ok'=>true, 'reply'=>$reply], JSON_UNESCAPED_UNICODE);
    exit;
}

/* تاريخ الشات */
if ($page === 'chat_history' && isLogged()) {
    header('Content-Type: application/json');
    echo json_encode(fetchAll("SELECT role,message FROM ai_messages WHERE user_id=? ORDER BY id ASC LIMIT 200", [$_SESSION['user_id']]), JSON_UNESCAPED_UNICODE);
    exit;
}

$user = currentUser();
$f = flash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= SITE_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
<style>
* { font-family: 'Cairo', sans-serif; }
body { background: #f9fafb; }
.g-hero { background: linear-gradient(135deg, #065f46 0%, #047857 50%, #0f766e 100%); }
.g-primary { background: linear-gradient(135deg, #059669 0%, #0d9488 100%); }
.card-hover { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
.card-hover:hover { transform: translateY(-8px); box-shadow: 0 25px 50px rgba(5, 150, 105, 0.25); }
.fade { animation: fadeIn 0.8s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.float { animation: floatAnim 3s ease-in-out infinite; }
@keyframes floatAnim { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
.pulse { animation: pulseAnim 2s ease-in-out infinite; }
@keyframes pulseAnim { 0%,100%{box-shadow:0 0 0 0 rgba(5,150,105,.7)} 50%{box-shadow:0 0 0 20px rgba(5,150,105,0)} }
.chat-container { height: 55vh; min-height: 400px; overflow-y: auto; padding: 20px; }
.chat-container::-webkit-scrollbar { width: 6px; }
.chat-container::-webkit-scrollbar-thumb { background: #059669; border-radius: 3px; }
.bubble-user { background: linear-gradient(135deg, #059669, #0d9488); color: white; border-radius: 18px 18px 4px 18px; padding: 12px 18px; max-width: 80%; margin-right: auto; margin-bottom: 10px; word-wrap: break-word; white-space: pre-wrap; }
.bubble-ai { background: #f3f4f6; color: #1f2937; border-radius: 18px 18px 18px 4px; padding: 12px 18px; max-width: 80%; margin-left: auto; margin-bottom: 10px; word-wrap: break-word; white-space: pre-wrap; }
.typing span { display: inline-block; width: 8px; height: 8px; background: #059669; border-radius: 50%; margin: 0 3px; animation: bounceAnim 1.4s infinite; }
.typing span:nth-child(2) { animation-delay: 0.2s; }
.typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounceAnim { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-10px)} }
.progress-bar { height: 10px; border-radius: 5px; background: #e5e7eb; overflow: hidden; }
.progress-bar > div { height: 100%; background: linear-gradient(90deg, #059669, #0d9488); border-radius: 5px; }
</style>
</head>
<body>

<?php if ($f): ?>
<div id="flashMsg" class="fixed top-24 left-1/2 -translate-x-1/2 z-[100] <?= $f[0]==='error'?'bg-red-500':'bg-emerald-500' ?> text-white px-6 py-3 rounded-xl shadow-2xl font-bold fade">
    <?= e($f[1]) ?>
</div>
<script>setTimeout(()=>document.getElementById('flashMsg')?.remove(), 3000);</script>
<?php endif; ?>

<nav class="g-hero text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16 md:h-20 flex-wrap gap-2">
            <a href="?" class="flex items-center gap-3">
                <div class="w-10 h-10 md:w-12 md:h-12 bg-white/20 rounded-xl flex items-center justify-center text-xl md:text-2xl pulse">📚</div>
                <div>
                    <h1 class="text-base md:text-lg font-black">عائلة حنيش</h1>
                    <p class="text-[10px] text-emerald-100">منصة تعليمية ذكية</p>
                </div>
            </a>
            <div class="hidden md:flex items-center gap-5 text-sm">
                <a href="?" class="hover:text-emerald-200 font-semibold">الرئيسية</a>
                <a href="?page=courses" class="hover:text-emerald-200 font-semibold">المواد</a>
                <a href="?page=chat" class="hover:text-emerald-200 font-semibold">🤖 المدرّس</a>
                <?php if ($user && $user['role']==='student'): ?>
                    <a href="?page=dashboard" class="hover:text-emerald-200 font-semibold">لوحتي</a>
                <?php endif; ?>
                <?php if ($user && $user['role']==='parent'): ?>
                    <a href="?page=parent" class="hover:text-emerald-200 font-semibold">الأبناء</a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <a href="?page=admin" class="hover:text-emerald-200 font-semibold">👑 الأدمن</a>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($user): ?>
                    <span class="hidden md:inline-block text-xs bg-white/20 px-3 py-1 rounded-lg">👤 <?= e($user['full_name']) ?></span>
                    <a href="?page=logout" class="bg-red-500/80 hover:bg-red-600 px-3 py-2 rounded-lg font-semibold text-xs">خروج</a>
                <?php elseif (isAdmin()): ?>
                    <a href="?page=admin" class="bg-yellow-500 px-3 py-2 rounded-lg font-bold text-xs">👑 لوحة الأدمن</a>
                    <a href="?page=logout" class="bg-red-500/80 px-3 py-2 rounded-lg font-semibold text-xs">خروج</a>
                <?php else: ?>
                    <a href="?page=login" class="bg-white/20 hover:bg-white/30 px-3 py-2 rounded-lg font-semibold text-xs">دخول</a>
                    <a href="?page=register" class="bg-white text-emerald-800 px-3 py-2 rounded-lg font-bold text-xs">حساب جديد</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php

/* ============ الصفحات ============ */

if ($page === 'home'): ?>
<section class="g-hero text-white">
    <div class="max-w-7xl mx-auto px-4 py-16 md:py-24">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="fade">
                <div class="inline-block bg-white/20 px-4 py-2 rounded-full mb-6 text-sm font-bold">✨ منصة ذكية لجميع المراحل</div>
                <h1 class="text-4xl md:text-6xl font-black leading-tight mb-6">
                    تعلّم مع <span class="text-emerald-200">عائلة حنيش</span>
                    <span class="block mt-2 text-3xl md:text-5xl">بالذكاء الاصطناعي 🤖</span>
                </h1>
                <p class="text-lg mb-8 leading-relaxed">منصة شاملة من الصف الأول الابتدائي حتى الثالث الثانوي، مع مدرّس ذكي 24/7.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="?page=register" class="bg-white text-emerald-800 px-8 py-4 rounded-xl font-bold text-lg shadow-lg card-hover">🚀 ابدأ مجاناً</a>
                    <a href="?page=chat" class="bg-white/20 border-2 border-white/40 px-8 py-4 rounded-xl font-bold text-lg card-hover">🤖 جرّب المدرّس</a>
                </div>
                <div class="grid grid-cols-3 gap-6 mt-12">
                    <div><div class="text-4xl font-black text-emerald-200">+5000</div><div class="text-sm mt-1">طالب</div></div>
                    <div><div class="text-4xl font-black text-emerald-200">+1200</div><div class="text-sm mt-1">درس</div></div>
                    <div><div class="text-4xl font-black text-emerald-200">24/7</div><div class="text-sm mt-1">دعم</div></div>
                </div>
            </div>
            <div class="hidden md:block fade">
                <div class="bg-white/10 backdrop-blur-md rounded-3xl p-8 border border-white/20 float">
                    <div class="bg-white rounded-2xl p-6 text-gray-800">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 g-primary rounded-xl flex items-center justify-center text-2xl">🤖</div>
                            <div><h3 class="font-bold">المدرّس الذكي</h3><p class="text-xs text-green-500">● متصل الآن</p></div>
                        </div>
                        <div class="space-y-3">
                            <div class="bg-gray-100 p-3 rounded-xl text-sm">كيف أحل معادلة؟</div>
                            <div class="g-primary text-white p-3 rounded-xl text-sm">استخدم: x = (-b ± √(b²-4ac)) / 2a</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-3xl md:text-4xl font-black text-center mb-12">المراحل <span class="text-emerald-600">الدراسية</span></h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php $stages = [
                ['الابتدائية','📚','من الأول للسادس','from-emerald-500 to-teal-600'],
                ['الإعدادية','🎓','من الأول للثالث','from-blue-500 to-indigo-600'],
                ['الثانوية','🏆','من الأول للثالث','from-purple-500 to-pink-600'],
                ['الثانوية العامة','⭐','الصف الثالث الثانوي','from-amber-500 to-orange-600'],
            ]; foreach ($stages as $s): ?>
            <div class="bg-gradient-to-br <?= $s[3] ?> text-white rounded-2xl p-6 card-hover">
                <div class="text-5xl mb-3"><?= $s[1] ?></div>
                <h3 class="font-bold text-lg mb-1"><?= $s[0] ?></h3>
                <p class="text-xs opacity-90"><?= $s[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-3xl md:text-4xl font-black text-center mb-12">مميزاتنا</h2>
        <div class="grid md:grid-cols-3 gap-6">
            <?php $feats = [
                ['🤖','مدرّس ذكي 24/7','اسأل الذكاء الاصطناعي في أي وقت'],
                ['📚','جميع المراحل','من الأول الابتدائي حتى الثالث الثانوي'],
                ['👨‍👩‍👧','متابعة أولياء الأمور','تقارير تفصيلية عن المستوى'],
                ['📝','اختبارات تفاعلية','اختبر مستواك بعد كل درس'],
                ['📱','متاح على كل الأجهزة','استخدم من الجوال أو الكمبيوتر'],
                ['🏅','شهادات إتمام','احصل على شهادة عند الإكمال'],
            ]; foreach ($feats as $f): ?>
            <div class="bg-white rounded-2xl p-8 shadow-lg card-hover">
                <div class="text-5xl mb-3"><?= $f[0] ?></div>
                <h3 class="text-xl font-bold mb-2"><?= $f[1] ?></h3>
                <p class="text-gray-600"><?= $f[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php elseif ($page === 'register'): ?>
<div class="g-hero min-h-screen flex items-center justify-center p-4 py-10">
    <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl p-8 fade">
        <div class="text-center mb-6">
            <div class="w-16 h-16 g-primary rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">📚</div>
            <h1 class="text-2xl font-black">إنشاء حساب جديد</h1>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <div><label class="block font-bold mb-2">الاسم الكامل</label><input type="text" name="full_name" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none"></div>
            <div><label class="block font-bold mb-2">البريد الإلكتروني</label><input type="email" name="email" required class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none"></div>
            <div><label class="block font-bold mb-2">كلمة السر</label><input type="password" name="password" required minlength="6" class="w-full px-4 py-3 border-2 rounded-xl focus:border-emerald-500 outline-none"></div>
            <div><label class="block font-bold mb-2">نوع الحساب</label>
                <select name="role" id="roleSel" class="w-full px-4 py-3 border-2 rounded-xl">
                    <option value="student">👨‍🎓 طالب</option>
                    <option value="parent">👨‍👩‍👧 ولي أمر</option>
                </select>
            </div>
            <div id="gradeDiv"><label class="block font-bold mb-2">الصف الدراسي</label>
                <select name="grade" class="w-full px-4 py-3 border-2 rounded-xl">
                    <?php foreach ($gradesList as $g): ?><option value="<?= $g ?>"><?= $g ?></option><?php endforeach; ?>
                </select>
            </div>
            <div id="parentDiv" style="display:none"><label class="block font-bold mb-2">بريد ولي الأمر (اختياري)</label>
                <input type="email" name="parent
