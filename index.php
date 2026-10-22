
<?php
session_start();
require_once "db.php";

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? "");
    $pass = $_POST['password'] ?? "";

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($pass, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $pass = $_POST['password'] ?? "";

    if ($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
        $error = "Enter a valid name and email. Password must be at least 8 characters.";
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hash);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['name'] = $name;
            session_regenerate_id(true);
            header("Location: index.php");
            exit;
        } else {
            $error = "That email may already be registered.";
        }
    }
}

$loggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$name = $_SESSION['name'] ?? "there";
$mode = $_GET['mode'] ?? 'login';

if ($loggedIn && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_transaction'])) {
    $type = $_POST['type'] ?? 'expense';
    $category = trim($_POST['category'] ?? '');
    $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['transaction_date'] ?? date('Y-m-d');

    $validDate = DateTime::createFromFormat('!Y-m-d', $date);
    if (!in_array($type, ['income', 'expense'], true) || $category === '' ||
        $amount === false || $amount <= 0 || !$validDate ||
        $validDate->format('Y-m-d') !== $date) {
        $error = "Please enter valid transaction details.";
    } else {
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, category, amount, description, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdss", $userId, $type, $category, $amount, $description, $date);
        if ($stmt->execute()) {
            header("Location: index.php");
            exit;
        } else {
            $error = "Could not save the transaction.";
        }
    }
}

if ($loggedIn && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_transaction'])) {
    $id = (int)($_POST['transaction_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    header("Location: index.php");
    exit;
}

$transactions = [];
$income = 0;
$expenses = 0;
$monthlyExpenses = 0;
$month = date('m');
$year = date('Y');

if ($loggedIn) {
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC, id DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT type, COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? GROUP BY type");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        if ($row['type'] === 'income') $income = (float)$row['total'];
        if ($row['type'] === 'expense') $expenses = (float)$row['total'];
    }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? AND type = 'expense' AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
    $stmt->bind_param("iii", $userId, $month, $year);
    $stmt->execute();
    $monthlyExpenses = (float)$stmt->get_result()->fetch_assoc()['total'];
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$categories = ['Food','Transport','Shopping','Bills','Education','Health','Entertainment','Salary','Freelance','Other'];
$chartData = [];
foreach ($transactions as $t) {
    if ($t['type'] === 'expense') {
        $cat = $t['category'];
        $chartData[$cat] = ($chartData[$cat] ?? 0) + (float)$t['amount'];
    }
}

arsort($chartData);

// MoneyMate Smart Insights
$insights = [];

if (!$loggedIn) {
    $insights[] = "Log in to view your personalized spending insights.";
} elseif (empty($transactions)) {
    $insights[] = "Welcome to MoneyMate! Add your first transaction to start tracking your finances.";
} else {
    $balance = $income - $expenses;

    if ($expenses > $income) {
        $insights[] = "Your expenses are higher than your income. Review your spending to help manage your budget.";
    } elseif ($income > 0 && $expenses == 0) {
        $insights[] = "You haven't recorded any expenses yet. Add your spending to understand where your money goes.";
    } else {
        $insights[] = "Your recorded income is ₹" . number_format($income, 2) . " and your recorded expenses are ₹" . number_format($expenses, 2) . ".";
    }

    if (!empty($chartData)) {
        $topCategory = array_key_first($chartData);
        $topAmount = $chartData[$topCategory];

        $insights[] = "Your highest recorded spending category is " . $topCategory . " (₹" . number_format($topAmount, 2) . "). Review this category to spot possible savings.";
    }

    if ($monthlyExpenses > 0) {
        $insights[] = "Your expenses recorded this month total ₹" . number_format($monthlyExpenses, 2) . ". Keep tracking them to understand your monthly spending.";
    } else {
        $insights[] = "No expenses have been recorded this month yet.";
    }

    if ($balance > 0) {
        $insights[] = "Your recorded income is currently higher than your expenses by ₹" . number_format($balance, 2) . ".";
    } elseif ($balance < 0) {
        $insights[] = "Your recorded expenses exceed your income by ₹" . number_format(abs($balance), 2) . ". Consider reviewing your spending.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MoneyMate — Smart Expense Companion</title>
<link rel="stylesheet" href="style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php if (!$loggedIn): ?>
<div class="auth-page">
    <div class="auth-art">
        <div class="brand"><span class="brand-icon">M</span> MoneyMate</div>
        <div class="art-content">
            <span class="eyebrow">YOUR MONEY. YOUR FUTURE.</span>
            <h1>Make every<br>rupee <span>count.</span></h1>
            <p>A clearer picture of your finances starts here. Track your spending, set goals, and build better money habits.</p>
            <div class="mini-stats"><div><strong>Track</strong><small>Every expense</small></div><div><strong>Plan</strong><small>Every goal</small></div><div><strong>Grow</strong><small>Every day</small></div></div>
        </div>
        <div class="art-footer">SMARTER MONEY HABITS START HERE</div>
    </div>
    <div class="auth-form-wrap">
        <form class="auth-card" method="post" action="index.php?mode=<?= $mode === 'register' ? 'register' : 'login' ?>">
            <span class="eyebrow">WELCOME TO MONEYMATE</span>
            <h2><?= $mode === 'register' ? 'Create your account' : 'Welcome back' ?></h2>
            <p class="muted"><?= $mode === 'register' ? 'Start your personal finance journey.' : 'Sign in to your financial dashboard.' ?></p>
            <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if ($mode === 'register'): ?>
            <label>Full name<input name="name" placeholder="Your name" maxlength="100" required></label>
            <?php endif; ?>
            <label>Email address<input type="email" name="email" placeholder="you@example.com" maxlength="150" required></label>
            <label>Password<input type="password" name="password" placeholder="At least 8 characters" minlength="8" required></label>
            <button class="btn-primary full" name="<?= $mode === 'register' ? 'register' : 'login' ?>" value="1"><?= $mode === 'register' ? 'Create account →' : 'Sign in →' ?></button>
            <p class="switch-auth"><?= $mode === 'register' ? 'Already have an account?' : "Don't have an account?" ?> <a href="index.php?mode=<?= $mode === 'register' ? 'login' : 'register' ?>"><?= $mode === 'register' ? 'Sign in' : 'Create one' ?></a></p>
            <p class="tiny-note">Your finance journey, organised in one place.</p>
        </form>
    </div>
</div>
<?php else: ?>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="index.php"><span class="brand-icon">M</span> MoneyMate</a>
        <div class="side-label">WORKSPACE</div>
        <a class="nav-link active" href="#dashboard"><span>◫</span> Overview</a>
        <a class="nav-link" href="#transactions"><span>⇄</span> Transactions</a>
        <a class="nav-link" href="#insights"><span>✧</span> MoneyMate Insights</a>
        <div class="sidebar-bottom"><div class="avatar"><?= e(strtoupper(substr($name,0,1))) ?></div><div class="profile"><strong><?= e($name) ?></strong><small>Personal account</small></div><a class="logout" href="index.php?logout=1" title="Log out">↗</a></div>
    </aside>
    <main class="main-content">
        <header class="topbar"><div><span class="eyebrow">PERSONAL FINANCE</span><h1 id="dashboard">Your money, in focus.</h1><p class="muted">Here's your financial snapshot, <?= e($name) ?>.</p></div><div class="top-actions"><span class="today"><?= date('d M Y') ?></span><a href="#add-transaction" class="btn-primary">+ Add transaction</a></div></header>
        <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
        <section class="stat-grid">
            <article class="stat-card"><div class="stat-top"><span>Total income</span><span class="stat-icon green">↗</span></div><strong>₹<?= number_format($income,2) ?></strong><small>All recorded income</small></article>
            <article class="stat-card"><div class="stat-top"><span>Total expenses</span><span class="stat-icon red">↘</span></div><strong>₹<?= number_format($expenses,2) ?></strong><small>All recorded spending</small></article>
            <article class="stat-card"><div class="stat-top"><span>Available balance</span><span class="stat-icon blue">◈</span></div><strong>₹<?= number_format($income-$expenses,2) ?></strong><small>Income minus expenses</small></article>
            <article class="stat-card"><div class="stat-top"><span>This month's spending</span><span class="stat-icon gold">◷</span></div><strong>₹<?= number_format($monthlyExpenses,2) ?></strong><small><?= date('F Y') ?></small></article>
        </section>
        <section class="content-grid">
            <article class="panel chart-panel"><div class="panel-heading"><div><h2>Spending breakdown</h2><p class="muted">Expenses by category</p></div><span class="pill">All time</span></div>
                <?php if ($chartData): ?><div class="chart-wrap"><canvas id="expenseChart"></canvas></div><?php else: ?><div class="empty-state"><span>◌</span><strong>Your chart starts here</strong><p>Add an expense to see your spending breakdown.</p></div><?php endif; ?>
            </article>
            <article class="panel insight-panel" id="insights"><div class="panel-heading"><div><span class="eyebrow">SMART MONEY HABITS</span><h2>MoneyMate Insights</h2></div><span class="sparkle">✧</span></div>
                
<?php foreach ($insights as $insight): ?>
    <div class="insight-item">
        <span class="insight-dot"></span>
        <div>
            <p><?= e($insight) ?></p>
        </div>
    </div>
<?php endforeach; ?>
                
<div class="ai-insights-box">
    <button type="button" id="generateAiInsights">
        Generate AI Insights
    </button>

    <div id="aiInsightsResult" aria-live="polite"></div>
</div>

<script>
document.getElementById('generateAiInsights').addEventListener('click', async function () {
    const button = this;
    const result = document.getElementById('aiInsightsResult');

    button.disabled = true;
    button.textContent = 'Generating insights...';
    result.textContent = 'Please wait while Gemini analyses your totals...';

    const payload = {
        income: <?= json_encode((float)$income) ?>,
        expenses: <?= json_encode((float)$expenses) ?>,
        categories: <?= json_encode(
            $chartData,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>
    };

    try {
        const response = await fetch('ai_insights.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to generate insights.');
        }

        result.textContent = data.insights;
    } catch (error) {
        result.textContent = 'Could not generate insights: ' + error.message;
    } finally {
        button.disabled = false;
        button.textContent = 'Generate AI Insights';
    }
});
</script>

                <div class="insight-foot">Rule-based suggestions · Not financial advice</div>
            </article>
        </section>
        <section class="panel add-panel" id="add-transaction"><div class="panel-heading"><div><h2>Add a transaction</h2><p class="muted">Keep every rupee accounted for.</p></div></div>
            <form method="post" class="transaction-form">
                <label>Type<select name="type" required><option value="expense">Expense</option><option value="income">Income</option></select></label>
                <label>Category<select name="category" required><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select></label>
                <label>Amount (₹)<input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required></label>
                <label>Date<input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required></label>
                <label class="description-field">Description<input name="description" maxlength="255" placeholder="What was it for?"></label>
                <button class="btn-primary" name="add_transaction" value="1">Save transaction</button>
            </form>
        </section>
        <section class="panel transactions-panel" id="transactions"><div class="panel-heading"><div><h2>Recent transactions</h2><p class="muted">Your latest recorded activity</p></div><input id="transactionSearch" class="search-input" placeholder="Search transactions…"></div>
            <div class="table-scroll"><table><thead><tr><th>Transaction</th><th>Category</th><th>Date</th><th>Amount</th><th></th></tr></thead><tbody id="transactionRows">
            <?php foreach (array_slice($transactions,0,100) as $t): ?>
            <tr><td><strong><?= e($t['description'] ?: $t['category']) ?></strong><small class="row-sub"><?= e(ucfirst($t['type'])) ?></small></td><td><span class="category-chip"><?= e($t['category']) ?></span></td><td><?= e(date('d M Y',strtotime($t['transaction_date']))) ?></td><td class="<?= $t['type']==='income'?'amount-in':'amount-out' ?>"><?= $t['type']==='income'?'+':'−' ?>₹<?= number_format((float)$t['amount'],2) ?></td><td><form method="post" onsubmit="return confirm('Delete this transaction?')"><input type="hidden" name="transaction_id" value="<?= (int)$t['id'] ?>"><button class="delete-btn" name="delete_transaction" value="1" title="Delete transaction">×</button></form></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php if (!$transactions): ?><div class="empty-row">No transactions yet. Add one above to get started.</div><?php endif; ?>
        </section>
        <footer class="footer">MoneyMate © <?= date('Y') ?> · Build better money habits, one day at a time.</footer>
    </main>
</div>
<script>
const canvas = document.getElementById('expenseChart');
if (canvas) {
  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_keys($chartData), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,
      datasets: [{data: <?= json_encode(array_values($chartData)) ?>, backgroundColor: ['#48d6a0','#6e9bff','#f5be68','#b39aff','#ff8398','#62c7df','#b5dc76','#f39b64','#e5a3e5','#93a1b5'], borderWidth: 0, hoverOffset: 7}]
    },
    options: {maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{color:'#b6c2d5',padding:18,usePointStyle:true,pointStyle:'circle'}}}, cutout:'70%'}
  });
}
</script>
<script src="script.js"></script>
<?php endif; ?>
</body>
</html>
