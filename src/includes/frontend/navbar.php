<?php

$cartCount = array_sum(
    $_SESSION['cart'] ?? []
);

$isLoggedIn = isset($_SESSION['customer_id']);

$customerName =
    $_SESSION['customer_name'] ?? '';

$currentPage = basename(
    $_SERVER['SCRIPT_NAME'] ?? ''
);

?>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark sticky-top shadow-sm py-2">

    <div class="container">

        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-shop me-1"></i>
            TechZone
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#frontendNavbar"
            aria-controls="frontendNavbar"
            aria-expanded="false"
            aria-label="Mở menu"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="frontendNavbar"
        >

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a
                        class="nav-link <?= $currentPage === 'index.php' ? 'active fw-semibold' : '' ?>"
                        href="/"
                    >
                        Trang chủ
                    </a>
                </li>

                <li class="nav-item">
                    <a
                        class="nav-link <?= $currentPage === 'products.php' ? 'active fw-semibold' : '' ?>"
                        href="/products.php"
                    >
                        Sản phẩm
                    </a>
                </li>

            </ul>

            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2">

                <a
                    class="btn btn-outline-light btn-sm position-relative"
                    href="/cart.php"
                >
                    <i class="bi bi-cart3 me-1"></i>
                    Giỏ hàng

                    <?php if ($cartCount > 0): ?>

                        <span class="badge rounded-pill bg-danger ms-1">
                            <?= (int) $cartCount ?>
                        </span>

                    <?php endif; ?>
                </a>

                <?php if ($isLoggedIn): ?>

                    <span class="text-light px-lg-2">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($customerName) ?>
                    </span>

                    <a
                        class="btn btn-outline-light btn-sm"
                        href="/logout.php"
                    >
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Đăng xuất
                    </a>

                <?php else: ?>

                    <a
                        class="btn btn-outline-light btn-sm"
                        href="/register.php"
                    >
                        Đăng ký
                    </a>

                    <a
                        class="btn btn-primary btn-sm"
                        href="/login.php"
                    >
                        <i class="bi bi-box-arrow-in-right me-1"></i>
                        Đăng nhập
                    </a>

                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <a
                        class="btn btn-outline-warning btn-sm"
                        href="/admin/"
                    >
                        <i class="bi bi-gear me-1"></i>
                        Quản trị
                    </a>
                <?php endif; ?>
            </div>

        </div>

    </div>

</nav>