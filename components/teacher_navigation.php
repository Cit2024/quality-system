<?php
// components/teacher_navigation.php
$currentPage = isset($currentPage) ? $currentPage : '';
?>
<div class="navigation">
    <ul>
        <li>
            <a href="#">
                <span class="icon">
                    <img src="./assets/icons/college.png" alt="college" />
                </span>
                <span class="title">نظام التقييم - المدرس</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage === 'dashboard') ? 'hovered' : ''; ?>">
            <a href="teacher_dashboard.php">
                <span class="icon">
                    <i class="fas fa-home"></i>
                </span>
                <span class="title">الصفحة الرئيسية</span>
            </a>
        </li>
        <li class="<?php echo ($currentPage === 'statistics') ? 'hovered' : ''; ?>">
            <a href="teacher_statistics.php">
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
