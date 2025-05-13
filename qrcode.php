<?php
// 需 composer require endroid/qr-code
require __DIR__ . '/vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$text = $_GET['text'] ?? '';
header('Content-Type: image/png');
$qr = QrCode::create($text)
    ->setSize(160)
    ->setMargin(10);
echo (new PngWriter())->write($qr)->getString();