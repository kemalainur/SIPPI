<?php
require_once 'config/database.php';

echo "--- ROLE MISMATCH CHECK ---\n";
$users = $pdo->query("SELECT p.nokta, p.nama, p.role_id as p_role_id, j.role_id as j_role_id, r.nama_role as j_role_name 
                      FROM tabel_pengurus p
                      LEFT JOIN tabel_pengurus_jabatan j ON p.nokta = j.nokta
                      LEFT JOIN tabel_role r ON j.role_id = r.id_role")->fetchAll();

foreach ($users as $u) {
    if ($u['p_role_id'] != $u['j_role_id']) {
        echo "MISMATCH! Name: {$u['nama']} | Nokta: {$u['nokta']} | p.role_id: " . var_export($u['p_role_id'], true) . " | j.role_id: " . var_export($u['j_role_id'], true) . " ({$u['j_role_name']})\n";
    } else {
        echo "MATCH: {$u['nama']} | Nokta: {$u['nokta']} | role_id: {$u['p_role_id']} ({$u['j_role_name']})\n";
    }
}
?>
