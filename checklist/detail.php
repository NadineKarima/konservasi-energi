<?php
// Halaman detail digabung ke form (checklist/form.php?id=). File ini dipertahankan agar tautan lama tetap bekerja.
require_once __DIR__ . '/../includes/functions.php';
login_wajib();
redirect('checklist/form.php?id=' . (int)($_GET['id'] ?? 0));
