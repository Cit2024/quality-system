<?php
// includes/navigation.php
$currentPage = isset($currentPage) ? $currentPage : '';
?>
<div class="navigation">
    <ul>
        <li>
            <a href="#">
                <span class="icon">
                    <img src="./assets/icons/college.png" alt="college" />
                </span>
                <span class="title">نظام إدارة النماذج الطالب</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage === 'dashboard') ? 'hovered' : ''; ?>">
            <a href="dashboard.php">
                <span class="icon">
                    <i class="fas fa-home"></i>
                </span>
                <span class="title">الصفحة الرئسية</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage === 'members') ? 'hovered' : ''; ?>">
            <a href="members.php">
                <span class="icon">
                    <i class="fa-solid fa-user"></i>
                </span>
                <span class="title">الأعضاء</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage === 'forms') ? 'hovered' : ''; ?>">
            <a href="forms.php">
                <span class="icon">
                    <i class="fa-solid fa-rectangle-list"></i>
                </span>
                <span class="title">النماذج</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage === 'statistics') ? 'hovered' : ''; ?>">
            <a href="statistics.php">
                <span class="icon">
                    <i class="fa-solid fa-chart-pie"></i>
                </span>
                <span class="title">الإحصائيات</span>
            </a>
        </li>
        <li>
            <a href="logout.php">
                <span class="icon">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </span>
                <span class="title">تسجيل الخروج</span>
            </a>
        </li>
    </ul>
</div>