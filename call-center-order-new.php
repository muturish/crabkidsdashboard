<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/data.php';

$page_title    = 'New Call Center Order';
$page_subtitle = 'Log a phone/WhatsApp order for later processing into a real POS sale';

$save_error = null;
$used_order = null; // call center order an M-Pesa code is already attached to

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name    = trim($_POST['customer_name'] ?? '');
    $customer_phone   = trim($_POST['customer_phone'] ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $delivery_method  = $_POST['delivery_method'] ?? '';
    $payment_status   = $_POST['payment_status'] ?? '';
    $payment_method   = trim($_POST['payment_method'] ?? '');
    $mpesa_receipt    = trim($_POST['mpesa_receipt_number'] ?? '');
    $agent_name       = trim($_POST['agent_name'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');
    $discount_amount  = is_numeric($_POST['discount_amount'] ?? null) ? max(0, (float)$_POST['discount_amount']) : 0;
    $shipping_amount  = is_numeric($_POST['shipping_amount'] ?? null) ? max(0, (float)$_POST['shipping_amount']) : 0;
    $items_json       = $_POST['items_json'] ?? '[]';
    $items            = json_decode($items_json, true);

    if ($customer_name === '' || $customer_phone === '') {
        $save_error = 'Customer name and phone are required.';
    } elseif (!in_array($delivery_method, ['delivery', 'parcel'], true)) {
        $save_error = 'Select a mode of delivery.';
    } elseif (!in_array($payment_status, ['pod', 'paid'], true)) {
        $save_error = 'Select a payment status.';
    } elseif ($delivery_method === 'parcel' && $payment_status !== 'paid') {
        $save_error = 'Parcel orders must be paid upfront -- select Paid.';
    } elseif ($payment_status === 'paid' && $payment_method !== 'mpesa') {
        $save_error = 'M-Pesa is the only payment method for paid orders.';
    } elseif ($payment_method === 'mpesa' && $mpesa_receipt === '') {
        $save_error = 'Search for and select the matching M-Pesa transaction.';
    } elseif ($payment_method === 'mpesa' && !find_mpesa_transaction($mpesa_receipt)) {
        $save_error = 'That M-Pesa transaction code was not found. Search again and pick a match.';
    } elseif ($payment_method === 'mpesa' && mpesa_receipt_used_in_pos($mpesa_receipt)) {
        $save_error = 'That M-Pesa transaction has already been used on a POS sale. Pick a different one.';
    } elseif ($payment_method === 'mpesa' && ($used_order = find_call_center_order_by_mpesa($mpesa_receipt))) {
        $save_error = 'M-Pesa transaction ' . $mpesa_receipt . ' has already been used on order ' . $used_order['order_no'] . ' and cannot be used twice.';
    } elseif (!is_array($items) || empty($items)) {
        $save_error = 'Add at least one item to the order.';
    } else {
        $clean_items = [];
        foreach ($items as $it) {
            $qty   = isset($it['quantity']) ? (int)$it['quantity'] : 0;
            $price = isset($it['unit_price']) ? (float)$it['unit_price'] : -1;
            $name  = trim((string)($it['product_name'] ?? ''));
            if ($name === '' || $qty < 1 || $price < 0) {
                $save_error = 'One of the items has an invalid quantity or price.';
                break;
            }
            $clean_items[] = [
                'variation_id'   => isset($it['variation_id']) && $it['variation_id'] !== '' ? (int)$it['variation_id'] : null,
                'product_name'   => $name,
                'variation_name' => trim((string)($it['variation_name'] ?? '')) ?: null,
                'sku'            => trim((string)($it['sku'] ?? '')) ?: null,
                'quantity'       => $qty,
                'unit_price'     => $price,
            ];
        }

        // A paid order's total must equal the M-Pesa payment it's matched to.
        // Same formula create_call_center_order() saves with.
        if (!$save_error && $payment_method === 'mpesa') {
            $order_total = 0.0;
            foreach ($clean_items as $ci) { $order_total += $ci['unit_price'] * $ci['quantity']; }
            $order_total = max(0, $order_total - $discount_amount + $shipping_amount);
            $mpesa_amount = (float)(find_mpesa_transaction($mpesa_receipt)['amount'] ?? 0);
            if (abs($order_total - $mpesa_amount) >= 0.01) {
                $save_error = 'The order total (KES ' . number_format($order_total) . ') does not match the M-Pesa amount (KES '
                    . number_format($mpesa_amount) . ') for ' . $mpesa_receipt . '. Adjust the items, discount or shipping so they match.';
            }
        }

        if (!$save_error) {
            try {
                $result = create_call_center_order([
                    'customer_name'    => $customer_name,
                    'customer_phone'   => $customer_phone,
                    'customer_address' => $customer_address,
                    'delivery_method'  => $delivery_method,
                    'payment_status'   => $payment_status,
                    'payment_method'   => $payment_method,
                    'mpesa_receipt_number' => $mpesa_receipt,
                    'agent_name'       => $agent_name,
                    'notes'            => $notes,
                    'discount_amount'  => $discount_amount,
                    'shipping_amount'  => $shipping_amount,
                ], $clean_items);
                header('Location: call-center-orders.php?created=' . urlencode($result['order_no']));
                exit;
            } catch (Exception $e) {
                // Another agent may have saved the same code a moment ago --
                // the unique key rejects it; report it like the check above.
                if ($payment_method === 'mpesa' && ($used_order = find_call_center_order_by_mpesa($mpesa_receipt))) {
                    $save_error = 'M-Pesa transaction ' . $mpesa_receipt . ' has already been used on order ' . $used_order['order_no'] . ' and cannot be used twice.';
                } else {
                    $save_error = $e->getMessage();
                }
            }
        }
    }
}

try {
    $payment_methods = get_call_center_payment_methods();
    $db_error = null;
} catch (Exception $e) {
    $payment_methods = [];
    $db_error = $e->getMessage();
}

$inline_scripts = <<<'JS'
(function(){
  var searchInput = document.getElementById('product-search');
  var results     = document.getElementById('search-results');
  var cartList     = document.getElementById('cart-list');
  var cartEmpty    = document.getElementById('cart-empty');
  var itemsOut     = document.getElementById('items-total');
  var subtotalOut  = document.getElementById('subtotal-total');
  var grandOut     = document.getElementById('grand-total');
  var discountIn   = document.getElementById('discount_amount');
  var shippingIn   = document.getElementById('shipping_amount');
  var itemsJson    = document.getElementById('items_json');
  var form         = document.getElementById('orderForm');

  var cart = [];
  var debounceTimer = null;

  function fmt(n){ return 'KES ' + Number(n || 0).toLocaleString(undefined,{maximumFractionDigits:2}); }
  function escapeHtml(s){ var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function computeTotals(){
    var count = 0, subtotal = 0;
    cart.forEach(function(it){ count += it.quantity; subtotal += it.quantity * it.unit_price; });
    var discount = Math.max(0, parseFloat(discountIn.value) || 0);
    var shipping = Math.max(0, parseFloat(shippingIn.value) || 0);
    var grand = Math.max(0, subtotal - discount + shipping);
    itemsOut.textContent = count;
    subtotalOut.textContent = fmt(subtotal);
    grandOut.textContent = fmt(grand);
    itemsJson.value = JSON.stringify(cart);
    orderTotal = grand;
    checkMpesaAmount();
  }

  // Paid orders: the order total must equal the selected M-Pesa payment.
  var orderTotal = 0;
  var selectedMpesaAmount = null;
  function mpesaAmountMismatch(){
    return selectedMpesaAmount !== null && Math.abs(orderTotal - selectedMpesaAmount) >= 0.01;
  }
  function checkMpesaAmount(){
    var el = document.getElementById('mpesa-amount-check');
    if (!el) return;
    if (selectedMpesaAmount === null) { el.hidden = true; el.innerHTML = ''; return; }
    el.hidden = false;
    if (!mpesaAmountMismatch()) {
      el.innerHTML = '<span class="ck-chip ck-chip-green"><i class="bi bi-check2-all me-1"></i>Order total matches the M-Pesa amount</span>';
    } else {
      var diff = orderTotal - selectedMpesaAmount;
      el.innerHTML = '<span class="ck-chip ck-chip-red"><i class="bi bi-exclamation-triangle me-1"></i>Order total ' + fmt(orderTotal) +
        ' &ne; M-Pesa ' + fmt(selectedMpesaAmount) + ' (' + fmt(Math.abs(diff)) + (diff > 0 ? ' over' : ' short') + ')</span>';
    }
  }

  function renderCart(){
    cartList.innerHTML = '';
    cartEmpty.hidden = cart.length > 0;
    cart.forEach(function(item){
      var max = Math.max(1, Math.floor(item.stock || 1));
      var row = document.createElement('div');
      row.className = 'cc-cart-row';
      row.innerHTML =
        '<div class="cc-cart-info">' +
          '<div class="cc-cart-name">' + escapeHtml(item.product_name) + (item.variation_name ? ' <span class="cc-cart-var">' + escapeHtml(item.variation_name) + '</span>' : '') + '</div>' +
          '<div class="cc-cart-price">' + fmt(item.unit_price) + ' each &middot; <span class="cc-cart-stock">' + max + ' in stock</span></div>' +
        '</div>' +
        '<div class="cc-qty">' +
          '<button type="button" class="cc-qty-btn minus" aria-label="Decrease">&minus;</button>' +
          '<input type="number" min="1" max="' + max + '" step="1" class="cc-qty-val" value="' + item.quantity + '">' +
          '<button type="button" class="cc-qty-btn plus" aria-label="Increase">+</button>' +
        '</div>' +
        '<div class="cc-cart-total">' + fmt(item.quantity * item.unit_price) + '</div>' +
        '<button type="button" class="cc-cart-remove" aria-label="Remove"><i class="bi bi-trash"></i></button>';

      var qtyInput = row.querySelector('.cc-qty-val');
      var minusBtn = row.querySelector('.minus');
      var plusBtn  = row.querySelector('.plus');
      var totalEl  = row.querySelector('.cc-cart-total');
      var stockEl  = row.querySelector('.cc-cart-stock');

      // Updates this row in place (no full re-render, so typing keeps focus).
      // Quantity is kept between 1 and the stock on hand.
      function setQty(q, keepTyping){
        var clamped = Math.min(max, Math.max(1, q));
        item.quantity = clamped;
        if (!keepTyping || clamped !== q) qtyInput.value = clamped;
        minusBtn.disabled = clamped <= 1;
        plusBtn.disabled  = clamped >= max;
        stockEl.textContent = clamped >= max ? 'max ' + max + ' in stock' : max + ' in stock';
        stockEl.classList.toggle('cc-cart-stock-max', clamped >= max);
        totalEl.textContent = fmt(clamped * item.unit_price);
        computeTotals();
      }
      minusBtn.addEventListener('click', function(){ setQty(item.quantity - 1); });
      plusBtn.addEventListener('click', function(){ setQty(item.quantity + 1); });
      qtyInput.addEventListener('input', function(){
        var q = parseInt(this.value, 10);
        if (!isNaN(q)) setQty(q, true); // empty while typing: wait for the next key
      });
      qtyInput.addEventListener('blur', function(){ setQty(parseInt(this.value, 10) || item.quantity); });
      qtyInput.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); this.blur(); } });
      row.querySelector('.cc-cart-remove').addEventListener('click', function(){ cart.splice(cart.indexOf(item), 1); renderCart(); });

      cartList.appendChild(row);
      setQty(item.quantity);
    });
    computeTotals();
  }

  function addToCart(p){
    var existing = cart.find(function(it){ return it.variation_id === p.variation_id; });
    if (existing) {
      existing.quantity = Math.min(parseFloat(p.stock) || existing.quantity, existing.quantity + 1);
    } else {
      cart.push({
        variation_id: p.variation_id,
        product_name: p.product_name,
        variation_name: p.variation_name,
        sku: p.sku,
        quantity: 1,
        unit_price: parseFloat(p.price) || 0,
        stock: parseFloat(p.stock) || 1
      });
    }
    renderCart();
  }

  function renderResults(products){
    results.innerHTML = '';
    if (!products.length) {
      results.innerHTML = '<div class="cc-search-empty">No matching products</div>';
      results.hidden = false;
      return;
    }
    products.forEach(function(p){
      var item = document.createElement('div');
      item.className = 'cc-search-item';
      item.innerHTML =
        '<div>' +
          '<div class="name">' + escapeHtml(p.product_name) + (p.variation_name ? ' &middot; ' + escapeHtml(p.variation_name) : '') + '</div>' +
          '<div class="meta">' + (p.sku ? escapeHtml(p.sku) + ' &middot; ' : '') + parseFloat(p.stock).toFixed(0) + ' in stock</div>' +
        '</div>' +
        '<div class="price">' + fmt(p.price) + '</div>';
      item.addEventListener('click', function(){
        addToCart(p);
        searchInput.value = '';
        results.hidden = true;
      });
      results.appendChild(item);
    });
    results.hidden = false;
  }

  function search(q){
    fetch('api/product-search.php?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){ renderResults(data.ok ? data.results : []); });
  }

  searchInput.addEventListener('input', function(){
    var q = this.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { results.hidden = true; results.innerHTML = ''; return; }
    debounceTimer = setTimeout(function(){ search(q); }, 250);
  });
  document.addEventListener('click', function(e){
    if (!results.contains(e.target) && e.target !== searchInput) results.hidden = true;
  });

  discountIn.addEventListener('input', computeTotals);
  shippingIn.addEventListener('input', computeTotals);

  var deliverySelect      = document.getElementById('delivery_method');
  var paymentStatusSelect = document.getElementById('payment_status');
  var podOption           = document.getElementById('ps-pod-option');
  var paymentMethodWrap   = document.getElementById('payment-method-wrap');
  var paymentMethodSelect = document.getElementById('payment_method');
  var mpesaSearch         = document.getElementById('mpesa-search');
  var mpesaResults        = document.getElementById('mpesa-results');
  var mpesaMatch          = document.getElementById('mpesa-match');
  var mpesaReceiptInput   = document.getElementById('mpesa_receipt_number');
  var mpesaDebounce       = null;

  function clearMpesaMatch(){
    mpesaReceiptInput.value = '';
    mpesaMatch.hidden = true;
    mpesaMatch.innerHTML = '';
    selectedMpesaAmount = null;
    checkMpesaAmount();
  }

  function selectMpesaTransaction(r){
    mpesaReceiptInput.value = r.receipt_number;
    mpesaSearch.value = r.receipt_number;
    mpesaResults.hidden = true;
    mpesaMatch.hidden = false;
    mpesaMatch.innerHTML = '<span class="ck-chip ck-chip-green"><i class="bi bi-check-circle me-1"></i>Matched: ' +
      escapeHtml(r.receipt_number) + (r.first_name ? ' &middot; ' + escapeHtml(r.first_name) : '') + ' &middot; ' + fmt(r.amount) + '</span>';
    selectedMpesaAmount = parseFloat(r.amount) || 0;
    checkMpesaAmount();
  }

  // A code already attached to another call center order can't be picked again --
  // say where it was used and link to that order instead.
  function showMpesaUsed(r){
    mpesaReceiptInput.value = '';
    selectedMpesaAmount = null;
    checkMpesaAmount();
    mpesaResults.hidden = true;
    mpesaMatch.hidden = false;
    mpesaMatch.innerHTML = '<div class="ck-alert err mb-0"><i class="bi bi-exclamation-octagon-fill"></i><div>' +
      'M-Pesa transaction <strong>' + escapeHtml(r.receipt_number) + '</strong> has already been used on order <strong>' +
      escapeHtml(r.used_order_no) + '</strong> and cannot be used twice. ' +
      '<a href="call-center-order-view.php?id=' + encodeURIComponent(r.used_order_id) + '" target="_blank" rel="noopener" class="fw-semibold">View order ' +
      escapeHtml(r.used_order_no) + ' &rarr;</a></div></div>';
  }

  function pickMpesa(r){ if (r.used_order_id) showMpesaUsed(r); else selectMpesaTransaction(r); }

  function usedBadge(r){
    return r.used_order_id ? ' <span class="ck-chip ck-chip-red">Used on ' + escapeHtml(r.used_order_no) + '</span>' : '';
  }

  function renderMpesaResults(rows){
    mpesaResults.innerHTML = '';
    if (!rows.length) {
      mpesaResults.innerHTML = '<div class="cc-search-empty">No matching M-Pesa payments found</div>';
      mpesaResults.hidden = false;
      return;
    }
    rows.forEach(function(r){
      var item = document.createElement('div');
      item.className = 'cc-search-item';
      var when = new Date(r.created_at.replace(' ', 'T')).toLocaleString(undefined, {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'});
      item.innerHTML =
        '<div>' +
          '<div class="name">' + escapeHtml(r.receipt_number) + (r.first_name ? ' &middot; ' + escapeHtml(r.first_name) : '') + usedBadge(r) + '</div>' +
          '<div class="meta">' + (r.mpesa_number ? escapeHtml(r.mpesa_number) + ' &middot; ' : '') + when + '</div>' +
        '</div>' +
        '<div class="price">' + fmt(r.amount) + '</div>';
      if (r.used_order_id) item.style.opacity = '0.6';
      item.addEventListener('click', function(){ pickMpesa(r); });
      mpesaResults.appendChild(item);
    });
    mpesaResults.hidden = false;
  }

  mpesaSearch.addEventListener('input', function(){
    clearMpesaMatch();
    var q = this.value.trim();
    clearTimeout(mpesaDebounce);
    if (q.length < 2) { mpesaResults.hidden = true; mpesaResults.innerHTML = ''; return; }
    mpesaDebounce = setTimeout(function(){
      fetch('api/mpesa-search.php?q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data){ renderMpesaResults(data.ok ? data.results : []); });
    }, 250);
  });
  document.addEventListener('click', function(e){
    if (!mpesaResults.contains(e.target) && e.target !== mpesaSearch) mpesaResults.hidden = true;
  });

  // Browse-all modal
  var mpesaModalEl     = document.getElementById('mpesaModal');
  var mpesaModalSearch = document.getElementById('mpesa-modal-search');
  var mpesaModalBody   = document.getElementById('mpesa-modal-body');
  var mpesaModalEmpty  = document.getElementById('mpesa-modal-empty');
  var mpesaModalDebounce = null;

  function loadMpesaModal(q){
    fetch('api/mpesa-search.php?limit=30' + (q ? '&q=' + encodeURIComponent(q) : ''))
      .then(function(r){ return r.json(); })
      .then(function(data){ renderMpesaModal(data.ok ? data.results : []); });
  }

  function renderMpesaModal(rows){
    mpesaModalBody.innerHTML = '';
    mpesaModalEmpty.hidden = rows.length > 0;
    rows.forEach(function(r){
      var when = new Date(r.created_at.replace(' ', 'T')).toLocaleString(undefined, {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'});
      var tr = document.createElement('tr');
      tr.style.cursor = 'pointer';
      tr.innerHTML =
        '<td class="fw-semibold">' + escapeHtml(r.receipt_number) + usedBadge(r) + '</td>' +
        '<td>' + (r.first_name ? escapeHtml(r.first_name) : '<span class="text-muted">&mdash;</span>') + '</td>' +
        '<td class="text-end fw-bold" style="color:#1d4ed8">' + fmt(r.amount) + '</td>' +
        '<td class="text-muted">' + when + '</td>';
      if (r.used_order_id) tr.style.opacity = '0.6';
      tr.addEventListener('click', function(){
        pickMpesa(r);
        bootstrap.Modal.getOrCreateInstance(mpesaModalEl).hide();
      });
      mpesaModalBody.appendChild(tr);
    });
  }

  mpesaModalEl.addEventListener('shown.bs.modal', function(){
    mpesaModalSearch.value = '';
    loadMpesaModal('');
    mpesaModalSearch.focus();
  });
  mpesaModalSearch.addEventListener('input', function(){
    var q = this.value.trim();
    clearTimeout(mpesaModalDebounce);
    mpesaModalDebounce = setTimeout(function(){ loadMpesaModal(q); }, 250);
  });

  // ── STK push: ask the POS to send an M-Pesa prompt for the order total to the
  // customer's phone, then poll until its callback marks it paid or failed.
  var stkBtn       = document.getElementById('stk-btn');
  var stkStatus    = document.getElementById('stk-status');
  var stkPoll      = null;
  var stkBtnLabel  = stkBtn.innerHTML;

  function stkShow(kind, html){
    stkStatus.hidden = false;
    stkStatus.className = 'cc-stk-status cc-stk-' + kind;
    stkStatus.innerHTML = html;
  }
  function stkStop(){
    if (stkPoll) { clearInterval(stkPoll); stkPoll = null; }
    stkBtn.disabled = false;
    stkBtn.innerHTML = stkBtnLabel;
  }

  stkBtn.addEventListener('click', function(){
    var phone = document.querySelector('[name=customer_phone]').value.trim();
    if (cart.length === 0) { alert('Add the items first -- the prompt is sent for the order total.'); return; }
    if (!phone) { alert('Enter the customer\'s phone number first.'); document.querySelector('[name=customer_phone]').focus(); return; }
    if (orderTotal < 1 || orderTotal !== Math.round(orderTotal)) { alert('The order total must be a whole number of shillings to send an M-Pesa prompt.'); return; }
    if (!confirm('Send an M-Pesa prompt for ' + fmt(orderTotal) + ' to ' + phone + '?')) return;

    stkStop();
    mpesaSearch.value = '';
    clearMpesaMatch();
    stkBtn.disabled = true;
    stkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
    stkShow('wait', 'Sending prompt…');

    var amount = orderTotal;
    fetch('api/mpesa-stk.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({phone: phone, amount: amount})})
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (!d.ok) { stkStop(); stkShow('fail', '<i class="bi bi-x-circle me-1"></i>' + escapeHtml(d.error || 'Prompt not sent.')); return; }
        stkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Waiting for PIN…';
        stkShow('wait', '<i class="bi bi-phone me-1"></i>Prompt for ' + fmt(amount) + ' sent to ' + escapeHtml(phone) + '. Ask the customer to enter their M-Pesa PIN…');
        var tries = 0;
        stkPoll = setInterval(function(){
          tries++;
          fetch('api/mpesa-stk-status.php?id=' + encodeURIComponent(d.checkout_request_id))
            .then(function(r){ return r.json(); })
            .then(function(s){
              if (!s.ok) return;
              if (s.status === 'success') {
                stkStop();
                selectMpesaTransaction({receipt_number: s.receipt_number, first_name: s.first_name, amount: s.amount});
                stkShow('ok', '<i class="bi bi-check-circle me-1"></i>Paid -- ' + escapeHtml(s.receipt_number) + ' &middot; ' + fmt(s.amount));
              } else if (s.status === 'failed') {
                stkStop();
                stkShow('fail', '<i class="bi bi-x-circle me-1"></i>Payment not completed' + (s.result_desc ? ': ' + escapeHtml(String(s.result_desc).replace(/\.+$/, '')) : '') + '. You can send it again.');
              } else if (tries >= 40) { // ~2 minutes
                stkStop();
                stkShow('fail', '<i class="bi bi-clock me-1"></i>No confirmation yet. If the customer paid, find it with the search below; otherwise send the prompt again.');
              }
            });
        }, 3000);
      })
      .catch(function(){ stkStop(); stkShow('fail', '<i class="bi bi-x-circle me-1"></i>Could not send the prompt. Check the connection and try again.'); });
  });

  function syncPaymentMethod(){
    if (paymentStatusSelect.value === 'paid') {
      paymentMethodWrap.hidden = false;
      paymentMethodSelect.required = true;
      paymentMethodSelect.value = 'mpesa';
    } else {
      paymentMethodWrap.hidden = true;
      paymentMethodSelect.required = false;
      paymentMethodSelect.value = '';
      stkStop();
      stkStatus.hidden = true;
      mpesaSearch.value = '';
      clearMpesaMatch();
    }
  }

  function syncDeliveryRule(){
    var isParcel = deliverySelect.value === 'parcel';
    podOption.disabled = isParcel;
    if (isParcel && paymentStatusSelect.value !== 'paid') paymentStatusSelect.value = 'paid';
    syncPaymentMethod();
  }

  deliverySelect.addEventListener('change', syncDeliveryRule);
  paymentStatusSelect.addEventListener('change', syncPaymentMethod);
  syncDeliveryRule();

  form.addEventListener('submit', function(e){
    if (cart.length === 0) {
      e.preventDefault();
      alert('Add at least one item to the order.');
      searchInput.focus();
      return;
    }
    if (paymentMethodSelect.value === 'mpesa' && !mpesaReceiptInput.value) {
      e.preventDefault();
      alert('Search for and select the matching M-Pesa transaction.');
      mpesaSearch.focus();
      return;
    }
    if (paymentMethodSelect.value === 'mpesa' && mpesaAmountMismatch()) {
      e.preventDefault();
      alert('The order total (' + fmt(orderTotal) + ') must match the M-Pesa amount (' + fmt(selectedMpesaAmount) + '). Adjust the items, discount or shipping.');
    }
  });

  renderCart();
})();
JS;

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($save_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><?= htmlspecialchars($save_error) ?><?php if ($used_order): ?> <a href="call-center-order-view.php?id=<?= (int)$used_order['id'] ?>" target="_blank" rel="noopener" class="fw-semibold">View order <?= htmlspecialchars($used_order['order_no']) ?> &rarr;</a><?php endif; ?></div></div>
<?php endif; ?>
<?php if ($db_error): ?>
<div class="ck-alert err"><i class="bi bi-x-circle-fill"></i><div><strong>Database error:</strong> <?= htmlspecialchars($db_error) ?></div></div>
<?php endif; ?>

<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
  <div><h1 class="ck-page-title"><?= htmlspecialchars($page_title) ?></h1><p class="ck-page-sub mb-0"><?= htmlspecialchars($page_subtitle) ?></p></div>
  <a href="call-center-orders.php" class="btn btn-ck-ghost btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
</div>

<form id="orderForm" method="POST" action="">
  <input type="hidden" name="items_json" id="items_json" value="[]">

  <div class="row g-3">
    <div class="col-lg-8">

      <div class="card mb-3">
        <div class="card-body cc-customer-bar">
          <div class="row g-2 align-items-center">
            <div class="col-md-4 col-xl-3">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="customer_name" class="form-control" placeholder="Name" aria-label="Customer name" title="Customer name" required value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
              </div>
            </div>
            <div class="col-md-3 col-xl-3">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                <input type="tel" name="customer_phone" class="form-control" placeholder="Phone" aria-label="Phone number" title="Phone number" required value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
              </div>
            </div>
            <div class="col-md-5 col-xl">
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                <input type="text" name="customer_address" class="form-control" placeholder="Address (optional)" aria-label="Delivery address (optional)" title="Delivery address (optional)" value="<?= htmlspecialchars($_POST['customer_address'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
          <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-green"><i class="bi bi-bag-plus"></i></span>Items</h6>
        </div>
        <div class="card-body">
          <div class="cc-search-wrap">
            <i class="bi bi-search cc-search-icon"></i>
            <input type="text" id="product-search" class="form-control cc-search-input" placeholder="Search by product name or SKU to add an item…" autocomplete="off">
            <div id="search-results" class="cc-search-results" hidden></div>
          </div>

          <div id="cart-list" class="mt-3"></div>
          <div id="cart-empty" class="ck-empty" style="padding:2rem 0;">
            <div class="ck-empty-icon"><i class="bi bi-cart"></i></div>
            <p class="mb-0 fw-semibold">No items added yet</p>
            <small class="text-muted">Search above and click a result to add it.</small>
          </div>
        </div>
      </div>

    </div>

    <div class="col-lg-4">
      <div class="cc-summary">
        <div class="card mb-3 cc-pay-card">
          <div class="card-header d-flex align-items-center gap-2">
            <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-orange"><i class="bi bi-truck"></i></span>Delivery &amp; Payment</h6>
          </div>
          <div class="card-body">
            <label class="form-label ck-kpi-label">Mode of Delivery</label>
            <select name="delivery_method" id="delivery_method" class="form-select form-select-sm" required>
              <option value="" selected disabled>Select mode of delivery…</option>
              <option value="delivery">Delivery</option>
              <option value="parcel">Parcel</option>
            </select>
            <small class="cc-pay-note">Parcel orders must be paid upfront via M-Pesa.</small>

            <label class="form-label ck-kpi-label">Payment Status</label>
            <select name="payment_status" id="payment_status" class="form-select form-select-sm mb-2" required>
              <option value="" selected disabled>Select payment status…</option>
              <option value="pod" id="ps-pod-option">Payment on Delivery</option>
              <option value="paid">Paid</option>
            </select>

            <div id="payment-method-wrap" hidden class="mb-2">
              <label class="form-label ck-kpi-label">Payment Method</label>
              <select name="payment_method" id="payment_method" class="form-select form-select-sm mb-2">
                <option value="" selected disabled>Select payment method…</option>
                <?php foreach ($payment_methods as $key => $label): ?>
                  <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>

              <label class="form-label ck-kpi-label">M-Pesa Transaction</label>
              <input type="hidden" name="mpesa_receipt_number" id="mpesa_receipt_number" value="">
              <button type="button" id="stk-btn" class="btn btn-success btn-sm w-100 mb-1"><i class="bi bi-phone-vibrate me-1"></i>Send STK Push to customer</button>
              <div id="stk-status" class="cc-stk-status" hidden></div>
              <div class="cc-stk-or">or find a Paybill payment</div>
              <div class="d-flex gap-2">
                <div class="cc-search-wrap flex-grow-1">
                  <i class="bi bi-search cc-search-icon"></i>
                  <input type="text" id="mpesa-search" class="form-control form-control-sm cc-search-input" placeholder="Search by M-Pesa code, phone, or name…" autocomplete="off">
                  <div id="mpesa-results" class="cc-search-results" hidden></div>
                </div>
                <button type="button" class="btn btn-ck-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#mpesaModal" title="Browse M-Pesa transactions"><i class="bi bi-list-ul"></i></button>
              </div>
              <div id="mpesa-match" class="mt-2" hidden></div>
              <div id="mpesa-amount-check" class="mt-1" hidden></div>
            </div>

            <label class="form-label ck-kpi-label">Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Anything the fulfilling team should know"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="bi bi-check-circle me-1"></i>Create Order</button>
        <a href="call-center-orders.php" class="btn btn-ck-ghost w-100 mt-2">Cancel</a>
      </div>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-header d-flex align-items-center gap-2">
      <h6 class="mb-0 fw-bold d-flex align-items-center gap-2 fs-6"><span class="ck-ci ck-ci-amber"><i class="bi bi-receipt"></i></span>Order Summary</h6>
    </div>
    <div class="card-body">
      <div class="cc-summary-bar">
        <div class="cc-summary-stat">
          <span class="cc-summary-label">Items</span>
          <span class="cc-summary-value" id="items-total">0</span>
        </div>
        <div class="cc-summary-stat">
          <span class="cc-summary-label">Subtotal</span>
          <span class="cc-summary-value" id="subtotal-total">KES 0</span>
        </div>
        <div class="cc-summary-stat">
          <label class="cc-summary-label" for="discount_amount">Discount</label>
          <input type="number" name="discount_amount" id="discount_amount" min="0" step="1" value="0" class="cc-summary-input">
        </div>
        <div class="cc-summary-stat">
          <label class="cc-summary-label" for="shipping_amount">Shipping</label>
          <input type="number" name="shipping_amount" id="shipping_amount" min="0" step="1" value="0" class="cc-summary-input">
        </div>
        <div class="cc-summary-stat cc-summary-grand">
          <span class="cc-summary-label">Total</span>
          <span class="cc-summary-value" id="grand-total">KES 0</span>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="modal fade" id="mpesaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold">Browse M-Pesa Transactions</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="cc-search-wrap mb-3">
          <i class="bi bi-search cc-search-icon"></i>
          <input type="text" id="mpesa-modal-search" class="form-control cc-search-input" placeholder="Showing last 3 days — type a code, phone, or name to search all dates…" autocomplete="off">
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th class="text-end">Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody id="mpesa-modal-body"></tbody>
          </table>
        </div>
        <div id="mpesa-modal-empty" class="ck-empty" style="padding:1.5rem 0;" hidden>
          <div class="ck-empty-icon"><i class="bi bi-inbox"></i></div>
          <p class="mb-0 fw-semibold">No M-Pesa transactions found</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
