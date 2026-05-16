<?php
session_start();
require_once __DIR__ . '/backend/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    if ($action === 'get_reservations') {
        $sql = "
            SELECT r.id,
                   r.seats_reserved,
                   r.total_price,
                   r.status AS reservation_status,
                   s.date AS screening_date,
                   s.time AS screening_time,
                   m.title AS movie_title,
                   p.id AS payment_id,
                   p.payment_status
            FROM reservations r
            JOIN screenings s ON r.screening_id = s.id
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN payments p ON p.reservation_id = r.id AND p.payment_status = 'paid'
            WHERE r.user_id = ?
            ORDER BY s.date ASC, s.time ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['error' => 'Erreur interne.']);
            exit;
        }

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $row['seat'] = $row['seats_reserved'] . ' place(s)';
            $row['date'] = date('d/m/Y H:i', strtotime($row['screening_date'] . ' ' . $row['screening_time']));
            $row['amount'] = number_format((float) $row['total_price'], 2, '.', '');
            $row['payment_status'] = $row['payment_status'] === 'paid' ? 'paid' : 'pending';
            $rows[] = $row;
        }

        echo json_encode($rows);
        exit;
    }

    if ($action === 'get_reservation') {
        $id = (int) ($_POST['id'] ?? 0);
        $sql = "
            SELECT r.id,
                   r.seats_reserved,
                   r.total_price,
                   r.status AS reservation_status,
                   s.date AS screening_date,
                   s.time AS screening_time,
                   m.title AS movie_title,
                   p.id AS payment_id,
                   p.payment_status
            FROM reservations r
            JOIN screenings s ON r.screening_id = s.id
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN payments p ON p.reservation_id = r.id AND p.payment_status = 'paid'
            WHERE r.id = ? AND r.user_id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['error' => 'Erreur interne.']);
            exit;
        }

        $stmt->bind_param('ii', $id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!$row) {
            echo json_encode(['error' => 'Réservation introuvable.']);
            exit;
        }

        $row['seat'] = $row['seats_reserved'] . ' place(s)';
        $row['date'] = date('d/m/Y H:i', strtotime($row['screening_date'] . ' ' . $row['screening_time']));
        $row['amount'] = number_format((float) $row['total_price'], 2, '.', '');
        $row['payment_status'] = $row['payment_status'] === 'paid' ? 'paid' : 'pending';

        echo json_encode($row);
        exit;
    }

    if ($action === 'pay') {
        $reservation_id = (int) ($_POST['reservation_id'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? '');
        $card_holder = trim($_POST['card_holder'] ?? '');
        $allowed_methods = ['card', 'cash', 'online'];

        if (!$reservation_id || !in_array($payment_method, $allowed_methods, true)) {
            echo json_encode(['error' => 'Données invalides.']);
            exit;
        }

        $stmt = $conn->prepare('SELECT total_price, status FROM reservations WHERE id = ? AND user_id = ? LIMIT 1');
        if (!$stmt) {
            echo json_encode(['error' => 'Erreur interne.']);
            exit;
        }

        $stmt->bind_param('ii', $reservation_id, $user_id);
        $stmt->execute();
        $reservation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reservation) {
            echo json_encode(['error' => 'Réservation introuvable.']);
            exit;
        }

        if ($reservation['status'] === 'paid') {
            echo json_encode(['error' => 'Cette réservation a déjà été payée.']);
            exit;
        }

        $stmt = $conn->prepare('SELECT id FROM payments WHERE reservation_id = ? AND payment_status = \"paid\" LIMIT 1');
        $stmt->bind_param('i', $reservation_id);
        $stmt->execute();
        $alreadyPaid = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($alreadyPaid) {
            echo json_encode(['error' => 'Cette réservation est déjà payée.']);
            exit;
        }

        $amount = (float) $reservation['total_price'];
        $status = 'paid';

        $stmt = $conn->prepare('INSERT INTO payments (reservation_id, payment_method, amount, payment_status, payment_date) VALUES (?, ?, ?, ?, NOW())');
        if (!$stmt) {
            echo json_encode(['error' => 'Impossible de traiter le paiement.']);
            exit;
        }

        $stmt->bind_param('isds', $reservation_id, $payment_method, $amount, $status);
        $stmt->execute();
        $payment_id = $conn->insert_id;
        $stmt->close();

        $stmt = $conn->prepare('UPDATE reservations SET status = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $status, $reservation_id);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare(
            'SELECT p.id, p.reservation_id, p.payment_method, p.amount, p.payment_status, p.payment_date, r.seats_reserved, s.date AS screening_date, s.time AS screening_time
             FROM payments p
             JOIN reservations r ON r.id = p.reservation_id
             JOIN screenings s ON r.screening_id = s.id
             WHERE p.id = ? LIMIT 1'
        );

        $stmt->bind_param('i', $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($payment) {
            $payment['seat'] = $payment['seats_reserved'] . ' place(s)';
            $payment['date'] = date('d/m/Y H:i', strtotime($payment['screening_date'] . ' ' . $payment['screening_time']));
            $payment['amount'] = number_format((float) $payment['amount'], 2, '.', '');
            echo json_encode(['success' => true, 'payment' => $payment]);
            exit;
        }

        echo json_encode(['error' => 'Paiement enregistré, mais impossibilité de récupérer le reçu.']);
        exit;
    }

    echo json_encode(['error' => 'Action inconnue.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paiement des réservations</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f7f6f3;--surface:#ffffff;--surface2:#f0efe9;--border:#e2e0d8;--border2:#cccab8;--text:#1a1916;--text2:#6b6860;--text3:#9e9c93;--accent:#2563eb;--accent-bg:#eff4ff;--accent-border:#bfcfff;--success:#16a34a;--success-bg:#f0fdf4;--success-border:#bbf7d0;--warn:#d97706;--warn-bg:#fffbeb;--danger:#dc2626;--danger-bg:#fef2f2;--radius:10px;--radius-lg:16px;--shadow:0 1px 3px rgba(0,0,0,.07),0 4px 12px rgba(0,0,0,.05);--shadow-lg:0 4px 20px rgba(0,0,0,.10)}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 2rem;height:58px;display:flex;align-items:center;justify-content:space-between}
.nav-brand{font-size:15px;font-weight:600;letter-spacing:-.02em;display:flex;align-items:center;gap:8px}
.nav-brand svg{width:20px;height:20px;color:var(--accent)}
.nav-step{font-size:12px;color:var(--text3);font-family:'DM Mono',monospace}
.layout{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;max-width:1100px;margin:2rem auto;padding:0 1.5rem;width:100%;flex:1}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow)}
.panel-head{padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-head h2{font-size:14px;font-weight:600;letter-spacing:-.01em}
.panel-body{padding:1.25rem 1.4rem}
.res-list{display:flex;flex-direction:column;gap:10px}
.res-item{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:all .15s;position:relative;background:var(--surface)}
.res-item:hover{border-color:var(--accent);background:var(--accent-bg)}
.res-item.active{border-color:var(--accent);background:var(--accent-bg);box-shadow:0 0 0 2px var(--accent-border)}
.res-item.paid{opacity:.55;cursor:default;pointer-events:none}
.res-icon{width:40px;height:40px;border-radius:10px;background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.res-icon svg{width:18px;height:18px;color:var(--text2)}
.res-info{flex:1;min-width:0}
.res-seat{font-size:14px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.res-meta{font-size:12px;color:var(--text3);margin-top:2px;font-family:'DM Mono',monospace}
.res-amount{font-size:15px;font-weight:600;font-family:'DM Mono',monospace;color:var(--text)}
.badge{font-size:11px;font-weight:500;padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px}
.badge-paid{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border)}
.badge-pending{background:var(--warn-bg);color:var(--warn);border:1px solid #fde68a}
.pay-panel{display:flex;flex-direction:column;gap:12px;position:sticky;top:1.5rem;align-self:start}
.summary{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow)}
.summary-header{padding:1rem 1.25rem;background:var(--surface2);border-bottom:1px solid var(--border)}
.summary-header h3{font-size:13px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em}
.summary-body{padding:1rem 1.25rem}
.summary-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:13.5px;border-bottom:1px dashed var(--border)}
.summary-row:last-child{border:none}
.summary-row .label{color:var(--text2)}
.summary-row .val{font-weight:500;font-family:'DM Mono',monospace;font-size:13px}
.summary-total{display:flex;justify-content:space-between;align-items:center;padding:12px 1.25rem;background:var(--accent-bg);border-top:1px solid var(--accent-border)}
.summary-total span:first-child{font-size:13px;font-weight:600;color:var(--accent)}
.summary-total span:last-child{font-size:20px;font-weight:600;color:var(--accent);font-family:'DM Mono',monospace}
.empty{text-align:center;padding:2.5rem 1rem;color:var(--text3);font-size:14px}
.empty svg{width:40px;height:40px;margin:0 auto 10px;display:block;opacity:.3}
.methods{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:10px 0}
.method{border:1px solid var(--border);border-radius:var(--radius);padding:12px 6px;text-align:center;cursor:pointer;background:var(--surface);transition:all .15s}
.method svg{width:20px;height:20px;color:var(--text2);display:block;margin:0 auto 6px}
.method span{font-size:12px;color:var(--text2);font-weight:500}
.method.active{border-color:var(--accent);background:var(--accent-bg)}
.method.active svg,.method.active span{color:var(--accent)}
.form-group{margin-bottom:10px}
.form-group label{font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px}
.form-group input,.form-group select{width:100%;padding:9px 12px;font-size:13.5px;font-family:'DM Sans',sans-serif;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text);outline:none;transition:border-color .15s}
.form-group input:focus,.form-group select:focus{border-color:var(--accent)}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.btn-pay{width:100%;padding:13px;font-size:15px;font-weight:600;font-family:'DM Sans',sans-serif;border:none;border-radius:var(--radius);cursor:pointer;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .15s,transform .1s}
.btn-pay:hover{opacity:.9}
.btn-pay:active{transform:scale(.98)}
.btn-pay:disabled{opacity:.5;cursor:not-allowed}
.btn-pay svg{width:18px;height:18px}
.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
@keyframes spin{to{transform:rotate(360deg)}}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:100;padding:1rem;animation:fadeIn .2s}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal{background:var(--surface);border-radius:20px;max-width:440px;width:100%;box-shadow:var(--shadow-lg);overflow:hidden;animation:slideUp .25s ease}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-top{background:var(--success-bg);padding:2rem 1.5rem 1.5rem;text-align:center;border-bottom:1px solid var(--success-border)}
.check-circle{width:64px;height:64px;background:var(--success);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.check-circle svg{width:30px;height:30px;color:#fff}
.modal-top h2{font-size:20px;font-weight:600;color:var(--success);margin-bottom:4px}
.modal-top p{font-size:13px;color:var(--text2)}
.modal-body{padding:1.25rem 1.5rem}
.receipt-row{display:flex;justify-content:space-between;font-size:13px;padding:7px 0;border-bottom:1px dashed var(--border)}
.receipt-row:last-child{border:none}
.receipt-row .rl{color:var(--text2)}
.receipt-row .rv{font-weight:500;font-family:'DM Mono',monospace;font-size:12.5px}
.pay-id{text-align:center;margin-top:10px;font-family:'DM Mono',monospace;font-size:11px;color:var(--text3);background:var(--surface2);padding:6px 12px;border-radius:6px}
.modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;gap:8px}
.btn-close{flex:1;padding:10px;border-radius:var(--radius);font-size:14px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;background:var(--surface2);border:1px solid var(--border);color:var(--text);transition:background .15s}
.btn-close:hover{background:var(--border)}
.alert-error{background:var(--danger-bg);border:1px solid #fecaca;border-radius:var(--radius);padding:10px 14px;font-size:13px;color:var(--danger);display:flex;align-items:center;gap:8px;margin-bottom:10px}
.alert-error svg{width:16px;height:16px;flex-shrink:0}
.skel{background:linear-gradient(90deg,var(--surface2) 25%,var(--border) 50%,var(--surface2) 75%);background-size:200% 100%;animation:skel 1.2s infinite;border-radius:6px;height:60px;margin-bottom:10px}
@keyframes skel{0%{background-position:200% 0}100%{background-position:-200% 0}}
@media(max-width:700px){.layout{grid-template-columns:1fr}.pay-panel{position:static}}
</style>
</head>
<body>
<nav>
  <div class="nav-brand">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h18M7 15h.01M11 15h.01M15 15h.01M3 6a1 1 0 011-1h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6z"/></svg>
    Rengoku.tv Paiement
  </div>
  <span class="nav-step" id="nav-step">Sélectionnez une réservation</span>
</nav>
<div class="layout">
  <div class="panel">
    <div class="panel-head">
      <h2>Réservations</h2>
      <span class="badge badge-pending" id="count-badge">chargement…</span>
    </div>
    <div class="panel-body">
      <div class="res-list" id="res-list">
        <div class="skel"></div>
        <div class="skel"></div>
        <div class="skel"></div>
      </div>
    </div>
  </div>
  <div class="pay-panel">
    <div class="summary" id="summary-card">
      <div class="summary-header"><h3>Résumé de la réservation</h3></div>
      <div class="summary-body">
        <div class="empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
          Choisissez une réservation
        </div>
      </div>
    </div>
    <div class="panel" id="payment-form-card" style="display:none">
      <div class="panel-head">
        <h2>Paiement</h2>
        <span class="badge badge-pending">En attente</span>
      </div>
      <div class="panel-body">
        <div id="alert-box" style="display:none"></div>
        <div class="form-group">
          <label>Méthode de paiement</label>
          <div class="methods">
            <div class="method active" data-method="card" onclick="selectMethod('card')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
              <span>Carte</span>
            </div>
            <div class="method" data-method="cash" onclick="selectMethod('cash')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/></svg>
              <span>Espèces</span>
            </div>
            <div class="method" data-method="online" onclick="selectMethod('online')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="17" r="1"/></svg>
              <span>En ligne</span>
            </div>
          </div>
        </div>
        <div id="card-fields">
          <div class="form-group">
            <label>Nom du titulaire</label>
            <input type="text" id="card-holder" placeholder="Ex: Ali Ben Salah">
          </div>
          <div class="form-group">
            <label>Numéro de carte</label>
            <input type="text" id="card-num" placeholder="1234 5678 9012 3456" maxlength="19">
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label>Date d'expiration</label>
              <input type="text" id="card-exp" placeholder="MM / AA" maxlength="7">
            </div>
            <div class="form-group">
              <label>CVV</label>
              <input type="text" id="card-cvv" placeholder="123" maxlength="4">
            </div>
          </div>
        </div>
        <button class="btn-pay" id="btn-pay" onclick="processPayment()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          Confirmer le paiement
        </button>
      </div>
    </div>
  </div>
</div>
<div class="overlay" id="success-overlay" style="display:none" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-top">
      <div class="check-circle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>
      </div>
      <h2>Paiement confirmé !</h2>
      <p>Le ticket est valide. Présentez-le à l'entrée.</p>
    </div>
    <div class="modal-body">
      <div class="receipt-row"><span class="rl">Réservation</span><span class="rv" id="ok-res-id">—</span></div>
      <div class="receipt-row"><span class="rl">Siège</span><span class="rv" id="ok-seat">—</span></div>
      <div class="receipt-row"><span class="rl">Date</span><span class="rv" id="ok-date">—</span></div>
      <div class="receipt-row"><span class="rl">Montant payé</span><span class="rv" id="ok-amount" style="color:var(--success)">—</span></div>
      <div class="receipt-row"><span class="rl">Méthode</span><span class="rv" id="ok-method">—</span></div>
      <div class="receipt-row"><span class="rl">Statut</span><span class="rv" style="color:var(--success)">✓ payé</span></div>
      <div class="receipt-row"><span class="rl">Date/heure</span><span class="rv" id="ok-created">—</span></div>
      <div class="pay-id" id="ok-pay-id"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-close" onclick="closeReceipt()">Nouvelle réservation</button>
    </div>
  </div>
</div>
<script>
let selectedReservation = null;
let selectedMethod = 'card';

document.addEventListener('DOMContentLoaded', () => {
  const requestedId = getQueryParam('reservation_id');
  loadReservations(requestedId);
  formatCardInputs();
});

async function loadReservations(selectedId = 0) {
  const fd = new FormData();
  fd.append('action', 'get_reservations');

  const res = await fetch('payment.php', { method: 'POST', body: fd });
  const data = await res.json();
  const list = document.getElementById('res-list');
  const cnt = document.getElementById('count-badge');

  if (!Array.isArray(data) || data.length === 0) {
    list.innerHTML = '<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>Aucune réservation disponible.</div>';
    cnt.textContent = '0 réservation';
    return;
  }

  if (selectedId) {
    const selectedItem = data.find((r) => Number(r.id) === Number(selectedId));
    if (selectedItem && selectedItem.payment_status !== 'paid') {
      setTimeout(() => selectReservation(selectedItem.id), 50);
    }
  }

  const pending = data.filter(r => r.payment_status !== 'paid');
  cnt.textContent = pending.length + ' en attente';

  list.innerHTML = data.map(r => {
    const isPaid = r.payment_status === 'paid';
    return `
      <div class="res-item ${isPaid ? 'paid' : ''}" onclick="selectReservation(${r.id})" data-id="${r.id}">
        <div class="res-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        </div>
        <div class="res-info">
          <div class="res-seat">${r.movie_title} · ${r.seat}</div>
          <div class="res-meta">#RES-${String(r.id).padStart(4,'0')} · ${r.date}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
          <div class="res-amount">${parseFloat(r.amount).toFixed(2)} $</div>
          ${isPaid ? '<span class="badge badge-paid">✓ Payé</span>' : '<span class="badge badge-pending">En attente</span>'}
        </div>
      </div>`;
  }).join('');
}

async function selectReservation(id) {
  document.querySelectorAll('.res-item').forEach(el => el.classList.remove('active'));
  const el = document.querySelector(`.res-item[data-id="${id}"]`);
  if (el) el.classList.add('active');

  document.getElementById('nav-step').textContent = 'Choisissez la méthode de paiement';

  const fd = new FormData();
  fd.append('action', 'get_reservation');
  fd.append('id', id);

  const res = await fetch('payment.php', { method: 'POST', body: fd });
  const data = await res.json();

  if (data.error) {
    alert(data.error);
    return;
  }

  selectedReservation = data;
  renderSummary(data);
  document.getElementById('payment-form-card').style.display = 'block';
  document.getElementById('alert-box').style.display = 'none';
}

function renderSummary(r) {
  const card = document.getElementById('summary-card');
  card.querySelector('.summary-body').innerHTML = `
    <div class="summary-row"><span class="label">Réservation</span><span class="val">#RES-${String(r.id).padStart(4,'0')}</span></div>
    <div class="summary-row"><span class="label">Film</span><span class="val">${escapeHtml(r.movie_title)}</span></div>
    <div class="summary-row"><span class="label">Siège</span><span class="val">${escapeHtml(r.seat)}</span></div>
    <div class="summary-row"><span class="label">Date</span><span class="val">${escapeHtml(r.date)}</span></div>
    <div class="summary-row"><span class="label">Statut</span><span class="val"><span class="badge badge-pending">En attente</span></span></div>
  `;
  card.innerHTML += `<div class="summary-total"><span>Montant total</span><span>${parseFloat(r.amount).toFixed(2)} $</span></div>`;
}

function selectMethod(method) {
  selectedMethod = method;
  document.querySelectorAll('.method').forEach(el => {
    el.classList.toggle('active', el.dataset.method === method);
  });
  document.getElementById('card-fields').style.display = method === 'card' ? 'block' : 'none';
}

async function processPayment() {
  if (!selectedReservation) return;

  const btn = document.getElementById('btn-pay');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Traitement…';

  const fd = new FormData();
  fd.append('action', 'pay');
  fd.append('reservation_id', selectedReservation.id);
  fd.append('payment_method', selectedMethod);
  fd.append('card_holder', document.getElementById('card-holder').value);

  const res = await fetch('payment.php', { method: 'POST', body: fd });
  const data = await res.json();

  btn.disabled = false;
  btn.innerHTML = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    Confirmer le paiement`;

  if (data.error) {
    const box = document.getElementById('alert-box');
    box.style.display = 'flex';
    box.innerHTML = `<div class="alert-error"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>${escapeHtml(data.error)}</div>`;
    return;
  }

  if (data.success) {
    const p = data.payment;
    document.getElementById('ok-res-id').textContent = '#RES-' + String(p.reservation_id).padStart(4,'0');
    document.getElementById('ok-seat').textContent = p.seat;
    document.getElementById('ok-date').textContent = p.date;
    document.getElementById('ok-amount').textContent = parseFloat(p.amount).toFixed(2) + ' $';
    document.getElementById('ok-method').textContent = p.payment_method;
    document.getElementById('ok-created').textContent = p.payment_date;
    document.getElementById('ok-pay-id').textContent = 'ID paiement : PAY-' + String(p.id).padStart(6,'0');
    document.getElementById('success-overlay').style.display = 'flex';
    loadReservations();
  }
}

function closeModal(e) {
  if (e.target === document.getElementById('success-overlay')) closeReceipt();
}
function closeReceipt() {
  document.getElementById('success-overlay').style.display = 'none';
  document.getElementById('payment-form-card').style.display = 'none';
  selectedReservation = null;
  document.querySelectorAll('.res-item').forEach(el => el.classList.remove('active'));
  document.getElementById('summary-card').querySelector('.summary-body').innerHTML =
    '<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>Choisissez une réservation</div>';
  document.getElementById('nav-step').textContent = 'Sélectionnez une réservation';
}

function getQueryParam(name) {
  const params = new URLSearchParams(window.location.search);
  return params.get(name) || '';
}

function getQueryParam(name) {
  const params = new URLSearchParams(window.location.search);
  return params.get(name) || '';
}

function formatCardInputs() {
  const num = document.getElementById('card-num');
  if (num) {
    num.addEventListener('input', () => {
      let v = num.value.replace(/\D/g, '').substring(0,16);
      num.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
  }
  const exp = document.getElementById('card-exp');
  if (exp) {
    exp.addEventListener('input', () => {
      let v = exp.value.replace(/\D/g, '').substring(0,4);
      if (v.length >= 2) v = v.substring(0,2) + ' / ' + v.substring(2);
      exp.value = v;
    });
  }
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
</script>
</body>
</html>