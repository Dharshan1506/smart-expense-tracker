<?php
/**
 * Global Header Include - ExpenseIQ Modern Fintech SaaS
 * Defines HTML Head, Favicon, Fonts, Stylesheets and page title
 */
declare(strict_types=1);

if (!isset($pageTitle)) {
    $pageTitle = 'ExpenseIQ - Intelligent Financial Management';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ExpenseIQ - Premium Intelligent Personal Finance SaaS with real-time analytics, budget monitoring, and DBMS relational showcase.">
    <title><?= htmlspecialchars($pageTitle) ?> | ExpenseIQ</title>

    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome 6 Icons CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Fintech SaaS Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-container">
