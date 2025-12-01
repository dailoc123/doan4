<?php
// Chatbox AI Gemini - Tối giản, độc lập, sẵn sàng include trên mọi trang
// Tự động xác định base path của ứng dụng (hỗ trợ chạy trong subfolder như /luxury_store1)
$cbx_base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
if ($cbx_base === '' || $cbx_base === '.') { $cbx_base = '/'; }
?>
<div class="cbx-container">
  <button class="cbx-open" id="cbx-open" title="AI Trợ lý">🤖</button>
  <div class="cbx-panel" id="cbx-panel" aria-hidden="true">
    <div class="cbx-header">
      <div class="cbx-title">AI Trợ lý Luxury Store</div>
      <button class="cbx-close" id="cbx-close" title="Đóng">✕</button>
    </div>
    <div class="cbx-chips" id="cbx-chips" aria-label="Gợi ý nhanh">
      <!-- Dùng 1 tầng danh mục: Nam=3, Nữ=4, Trẻ em=8 -->
      <button data-q="Áo nam"   data-type="ao"   data-gender-id="3">Áo nam</button>
      <button data-q="Áo nữ"    data-type="ao"   data-gender-id="4">Áo nữ</button>
      <button data-q="Quần nam" data-type="quan" data-gender-id="3">Quần nam</button>
      <button data-q="Quần nữ"  data-type="quan" data-gender-id="4">Quần nữ</button>
      <button data-q="Áo trẻ em"   data-type="ao"   data-gender-id="8">Áo trẻ em</button>
      <button data-q="Quần trẻ em" data-type="quan" data-gender-id="8">Quần trẻ em</button>
      <button data-q="Tư vấn đi">Tư vấn đi</button>
    </div>
    <div class="cbx-body" id="cbx-messages" aria-live="polite"></div>
    <div class="cbx-input">
      <input type="text" id="cbx-input" placeholder="Hỏi bất cứ điều gì..." aria-label="Tin nhắn">
      <button id="cbx-send" title="Gửi">➤</button>
    </div>
  </div>
</div>

<script>
(() => {
  const openBtn = document.getElementById('cbx-open');
  const panel   = document.getElementById('cbx-panel');
  const closeBtn= document.getElementById('cbx-close');
  const sendBtn = document.getElementById('cbx-send');
  const inputEl = document.getElementById('cbx-input');
  const msgEl   = document.getElementById('cbx-messages');
  const CBX_BASE = '<?php echo addslashes($cbx_base); ?>';
  const STORAGE_KEY = 'cbx-history:' + CBX_BASE;

  function persistSnapshot(){
    try{
      const items = [];
      msgEl.querySelectorAll(':scope > *').forEach(node => {
        if (node.classList.contains('cbx-msg')){
          const role = node.classList.contains('user') ? 'user' : 'bot';
          items.push({ kind:'msg', role, text: node.textContent });
        } else if (node.classList.contains('cbx-products')){
          const products = [];
          node.querySelectorAll('.cbx-card').forEach(card => {
            const a = card.querySelector('a.cbx-card-link');
            const img = card.querySelector('img');
            const nameEl = card.querySelector('.name');
            const priceEl = card.querySelector('.price');
            const href = a ? a.getAttribute('href') : '';
            const idMatch = href && href.match(/id=([^&]+)/);
            products.push({
              id: idMatch ? idMatch[1] : null,
              href,
              image_url: img ? img.getAttribute('src') : '',
              name: nameEl ? nameEl.textContent : '',
              price_text: priceEl ? priceEl.innerHTML : ''
            });
          });
          items.push({ kind:'products', products });
        }
      });
      const payload = { ts: Date.now(), open: panel.style.display==='flex', items };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    }catch(e){ /* ignore */ }
  }

  function restoreSnapshot(){
    try{
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      const data = JSON.parse(raw);
      if (!data || !Array.isArray(data.items)) return;
      // Restore open state
      toggle(!!data.open);
      // Recreate DOM
      data.items.forEach(it => {
        if (it.kind==='msg'){
          if (it.role==='user') user(it.text, false); else bot(it.text, false);
        } else if (it.kind==='products'){
          renderProducts(it.products, true);
        }
      });
    }catch(e){ /* ignore */ }
  }

  const bot = (text, persist=true) => {
    const d = document.createElement('div'); d.className='cbx-msg bot'; d.textContent = text; msgEl.appendChild(d); msgEl.scrollTop = msgEl.scrollHeight; if (persist) persistSnapshot(); };
  const user = (text, persist=true) => {
    const d = document.createElement('div'); d.className='cbx-msg user'; d.textContent = text; msgEl.appendChild(d); msgEl.scrollTop = msgEl.scrollHeight; if (persist) persistSnapshot(); };

  function renderProducts(products, restoring=false){
    if (!products || !products.length) return;
    const wrap = document.createElement('div');
    wrap.className = 'cbx-products';
    products.forEach(p => {
      const card = document.createElement('div');
      card.className = 'cbx-card';
      const href = 'product_detail.php?id=' + encodeURIComponent(p.id);

      const parseMoney = (v) => {
        if (v == null) return NaN;
        if (typeof v === 'number') return v;
        const s = String(v).replace(/[^\d]/g,'');
        return s ? parseInt(s,10) : NaN;
      };
      const rawDiscount = parseMoney(p.discount_price);
      const rawPrice    = parseMoney(p.price);
      const rawFinal    = parseMoney(p.final_price || p.price_final || p.sale_price);
      const displayVal  = [rawDiscount, rawFinal, rawPrice].find(n => Number.isFinite(n) && n>0);
      const strikeVal   = (Number.isFinite(rawPrice) && Number.isFinite(displayVal) && rawPrice>displayVal) ? rawPrice : null;
      const priceHTML   = displayVal ? `${formatPrice(displayVal)}${strikeVal ? ` <span class="strike">${formatPrice(strikeVal)}</span>`:''}` : (p.price_text || '');

      card.innerHTML = `
        <a class="cbx-card-link" href="${href}" target="_self">
          <img src="${p.image_url}" alt="${p.name}" loading="lazy" onerror="this.src='images/product-placeholder.jpg'"/>
          <div class="info">
            <div class="name">${p.name}</div>
            <div class="price">${priceHTML || ''}</div>
          </div>
        </a>
        <div class="actions">
          <button class="cbx-advise" data-id="${p.id}" data-name="${p.name}">Tư vấn</button>
          <button class="cbx-add" data-id="${p.id}">Thêm vào giỏ hàng</button>
        </div>
      `;
      const btn = card.querySelector('button.cbx-add');
      btn?.addEventListener('click', async (ev) => {
        ev.preventDefault(); ev.stopPropagation();
        const id = btn.getAttribute('data-id');
        await addToCart(id);
      });
      const adv = card.querySelector('button.cbx-advise');
      adv?.addEventListener('click', async (ev) => {
        ev.preventDefault(); ev.stopPropagation();
        const name = adv.getAttribute('data-name');
        const id   = adv.getAttribute('data-id');
        await adviseProduct({ id, name }, card);
      });
      wrap.appendChild(card);
    });
    msgEl.appendChild(wrap);
    msgEl.scrollTop = msgEl.scrollHeight;
    if (!restoring) persistSnapshot();
  }

  async function addToCart(id){
    try{
      const res = await fetch((CBX_BASE.replace(/\/$/,'') + '/add_to_cart.php'), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id })
      });
      const data = await res.json();
      bot(data.message || (data.success ? 'Đã thêm vào giỏ hàng' : 'Không thể thêm vào giỏ hàng'));
    }catch(e){ bot('Có lỗi xảy ra khi thêm vào giỏ hàng'); }
  }

  async function adviseProduct(product, cardEl){
    try{
      bot(`Bạn muốn tư vấn size/màu cho "${product.name}"? Chọn bên dưới.`);
      // Tạo panel tư vấn nếu chưa có
      let panel = cardEl.querySelector('.cbx-consult');
      if (!panel){
        panel = document.createElement('div');
        panel.className = 'cbx-consult';
        panel.innerHTML = `
          <div class="consult-title">Tư vấn chi tiết</div>
          <div class="consult-row">
            <div class="label">Màu sắc</div>
            <div class="chips" data-kind="color"></div>
          </div>
          <div class="consult-row">
            <div class="label">Kích thước</div>
            <div class="chips" data-kind="size"></div>
          </div>
          <div class="consult-actions">
            <button class="consult-ask" data-type="fit">Hỏi tư vấn chọn size</button>
            <button class="consult-ask" data-type="stock">Hỏi tình trạng kho</button>
            <button class="consult-ask primary" data-type="general">Gửi câu hỏi</button>
          </div>
        `;
        cardEl.appendChild(panel);
      }
      // Loading
      const colorWrap = panel.querySelector('.chips[data-kind="color"]');
      const sizeWrap  = panel.querySelector('.chips[data-kind="size"]');
      colorWrap.innerHTML = '<span class="loading">Đang tải màu...</span>';
      sizeWrap.innerHTML  = '<span class="loading">Đang tải size...</span>';

      const url = new URL((CBX_BASE.replace(/\/$/,'') + '/get_product_quick_view.php'), window.location.origin);
      url.searchParams.set('id', product.id);
      const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      const p = data?.product;
      const colors = Array.isArray(p?.colors) ? p.colors : [];
      const sizes  = Array.isArray(p?.sizes) ? p.sizes : [];

      // Render chips
      colorWrap.innerHTML = '';
      if (colors.length){
        colors.forEach((c, idx) => {
          const b = document.createElement('button');
          b.className = 'chip';
          b.textContent = c.name;
          b.setAttribute('data-id', c.id);
          b.setAttribute('data-name', c.name);
          if (idx===0) b.classList.add('active');
          b.addEventListener('click', () => {
            colorWrap.querySelectorAll('.chip').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
          });
          colorWrap.appendChild(b);
        });
      } else {
        colorWrap.innerHTML = '<span class="muted">Không có màu cụ thể</span>';
      }

      sizeWrap.innerHTML = '';
      if (sizes.length){
        sizes.forEach((s, idx) => {
          const b = document.createElement('button');
          b.className = 'chip';
          b.textContent = s.name;
          b.setAttribute('data-id', s.id);
          b.setAttribute('data-name', s.name);
          if (idx===0) b.classList.add('active');
          b.addEventListener('click', () => {
            sizeWrap.querySelectorAll('.chip').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
          });
          sizeWrap.appendChild(b);
        });
      } else {
        sizeWrap.innerHTML = '<span class="muted">Không có size cụ thể</span>';
      }

      // Action buttons -> compose message and send
      panel.querySelectorAll('.consult-ask').forEach(btn => {
        btn.addEventListener('click', async () => {
          const selColor = colorWrap.querySelector('.chip.active')?.getAttribute('data-name') || '';
          const selSize  = sizeWrap.querySelector('.chip.active')?.getAttribute('data-name') || '';
          const type = btn.getAttribute('data-type');
          let message;
          if (type==='fit'){
            message = `Tư vấn chọn size cho sản phẩm "${product.name}" (ID: ${product.id}). Size quan tâm: ${selSize || 'chưa chọn'}. Màu: ${selColor || 'chưa chọn'}.`;
          } else if (type==='stock'){
            message = `Tình trạng kho và còn hàng cho sản phẩm "${product.name}" (ID: ${product.id}), màu: ${selColor || 'bất kỳ'}, size: ${selSize || 'bất kỳ'}.`;
          } else {
            message = `Tư vấn chi tiết cho sản phẩm "${product.name}" (ID: ${product.id}) với lựa chọn màu: ${selColor || 'bất kỳ'}, size: ${selSize || 'bất kỳ'}.`;
          }
          inputEl.value = message;
          await send();
        });
      });
    } catch(e){
      inputEl.value = `Tư vấn cho sản phẩm \"${product.name}\" (ID: ${product.id}).`;
      await send();
    }
  }

  function formatPrice(v){ try{ return new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(Number(v||0)); }catch(e){ return (v||0)+'đ'; } }

  function toggle(open){
    panel.style.display = open ? 'flex' : 'none';
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) inputEl.focus();
    persistSnapshot();
  }

  openBtn?.addEventListener('click', () => toggle(panel.style.display !== 'flex'));
  closeBtn?.addEventListener('click', () => toggle(false));
  restoreSnapshot();

  async function send(){
    const text = (inputEl.value || '').trim();
    if (!text) return;
    user(text); inputEl.value='';
    bot('Đang suy nghĩ...');
    try {
      const res = await fetch((CBX_BASE.replace(/\/$/,'') + '/gemini_chat_api.php'), { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ message: text }) });
      const data = await res.json();
      const last = msgEl.querySelector('.cbx-msg.bot:last-child');
      if (data && data.success) {
        if (last) last.textContent = data.text; else bot(data.text);
        if (data.products && data.products.length){ renderProducts(data.products); }
      }
      else { if (last) last.textContent = 'Xin lỗi, không thể lấy phản hồi.'; }
    } catch (e){
      const last = msgEl.querySelector('.cbx-msg.bot:last-child');
      if (last) last.textContent = 'Lỗi kết nối server.';
    }
  }

  sendBtn?.addEventListener('click', send);
  inputEl?.addEventListener('keypress', e => { if (e.key==='Enter') send(); });

  // Chips hành động nhanh
  const chips = document.getElementById('cbx-chips');
  chips?.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-q]');
    if (!btn) return;
    const q = btn.getAttribute('data-q');
    const type = btn.getAttribute('data-type');
    const genderId = btn.getAttribute('data-gender-id');
    // Nếu chip có type/gender => tìm sản phẩm, ngược lại => gửi vào AI
    if (type || genderId){
      user(q);
      bot('Đang tìm sản phẩm phù hợp...');
      try{
        const url = new URL((CBX_BASE.replace(/\/$/,'') + '/ajax/products_ai.php'), window.location.origin);
        if (type)      url.searchParams.set('type', type);
        if (genderId)  url.searchParams.set('gender_id', genderId);
        url.searchParams.set('limit','8');
        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        if (!res.ok) {
          const last = msgEl.querySelector('.cbx-msg.bot:last-child');
          const text = await res.text();
          if (last) last.textContent = 'API lỗi ' + res.status + ': ' + (text?.slice(0,120) || 'Không rõ');
          return;
        }
        const data = await res.json();
        const last = msgEl.querySelector('.cbx-msg.bot:last-child');
        if (data && data.success){
          if (last) last.textContent = 'Dưới đây là các sản phẩm phù hợp:'; else bot('Dưới đây là các sản phẩm phù hợp:');
          renderProducts(data.products);
        } else {
          if (last) last.textContent = 'Không thể tải sản phẩm.';
        }
      }catch(err){
        const last = msgEl.querySelector('.cbx-msg.bot:last-child');
        if (last) last.textContent = 'Lỗi khi tải sản phẩm.';
      }
    } else {
      // Chip "Tư vấn đi"
      inputEl.value = q;
      await send();
    }
  });
})();
</script>

<style>
.cbx-container{position:fixed;right:18px;bottom:18px;z-index:9999;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
.cbx-open{width:44px;height:44px;border:none;border-radius:50%;background:#28a745;color:#fff;font-size:20px;box-shadow:0 6px 18px rgba(0,0,0,.2);cursor:pointer}
.cbx-open:hover{filter:brightness(1.05)}
.cbx-panel{display:none;flex-direction:column;position:fixed;right:18px;bottom:72px;width:360px;max-width:90vw;height:420px;background:#fff;border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.25);border:1px solid #e5e7eb}
.cbx-header{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#28a745;color:#fff;border-radius:12px 12px 0 0}
.cbx-title{font-weight:600;font-size:14px}
.cbx-close{border:none;background:transparent;color:#fff;font-size:18px;cursor:pointer}
.cbx-chips{display:flex;gap:6px;padding:8px 10px;background:#f1f5f9;border-bottom:1px solid #e5e7eb;flex-wrap:wrap}
.cbx-chips button{border:none;background:#e2e8f0;color:#0f172a;padding:6px 10px;border-radius:18px;cursor:pointer;font-size:12px}
.cbx-chips button:hover{filter:brightness(1.05)}
.cbx-body{flex:1;overflow:auto;padding:10px;background:#f8fafc}
.cbx-msg{margin:8px 0;padding:8px 10px;border-radius:10px;max-width:82%}
.cbx-msg.bot{background:#e5f7eb;color:#0f5132}
.cbx-msg.user{background:#e9e9ff;color:#1f1f77;margin-left:auto}
.cbx-products{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:8px}
.cbx-card{display:flex;flex-direction:column;gap:6px;text-decoration:none;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:8px}
.cbx-card img{width:100%;height:120px;object-fit:cover;border-radius:8px}
.cbx-card .info{display:flex;flex-direction:column;gap:2px}
.cbx-card .name{font-size:12px;color:#0f172a;line-height:1.3}
.cbx-card .price{font-size:12px;color:#16a34a;font-weight:600}
.cbx-card .strike{color:#64748b;font-weight:400;text-decoration:line-through;margin-left:4px}
.cbx-card .actions{display:flex;justify-content:space-between;gap:8px;margin-top:6px}
.cbx-card .cbx-add{border:none;background:#28a745;color:#fff;font-size:12px;padding:6px 8px;border-radius:6px;cursor:pointer}
.cbx-card .cbx-add:hover{filter:brightness(1.05)}
.cbx-card .cbx-advise{border:1px solid #1a73e8;background:#fff;color:#1a73e8;font-size:12px;padding:6px 8px;border-radius:6px;cursor:pointer}
.cbx-card .cbx-advise:hover{filter:brightness(1.05)}
.cbx-card .cbx-consult{margin-top:8px;border-top:1px dashed #e5e7eb;padding-top:8px;}
.cbx-card .consult-title{font-size:12px;color:#0f172a;margin-bottom:6px}
.cbx-card .consult-row{display:flex;align-items:flex-start;gap:6px;margin-bottom:6px}
.cbx-card .consult-row .label{font-size:12px;color:#475569;min-width:60px}
.cbx-card .consult-row .chips{display:flex;flex-wrap:wrap;gap:6px}
.cbx-card .consult-row .chips .chip{font-size:11px;padding:5px 8px;border:1px solid #e5e7eb;border-radius:999px;background:#fff;color:#0f172a;cursor:pointer}
.cbx-card .consult-row .chips .chip.active{border-color:#1a73e8;color:#1a73e8;background:#eef5ff}
.cbx-card .consult-row .chips .loading{font-size:12px;color:#64748b}
.cbx-card .consult-row .chips .muted{font-size:12px;color:#94a3b8}
.cbx-card .consult-actions{display:flex;gap:6px;margin-top:6px}
.cbx-card .consult-actions .consult-ask{border:1px solid #e5e7eb;background:#fff;color:#0f172a;font-size:12px;padding:6px 8px;border-radius:6px;cursor:pointer}
.cbx-card .consult-actions .consult-ask.primary{border-color:#1a73e8;color:#fff;background:#1a73e8}
.cbx-card .consult-actions .consult-ask:hover{filter:brightness(1.05)}
.cbx-input{display:flex;gap:8px;padding:10px;border-top:1px solid #e5e7eb;background:#fff;border-radius:0 0 12px 12px}
.cbx-input input{flex:1;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}
.cbx-input button{width:44px;border:none;border-radius:8px;background:#1a73e8;color:#fff;cursor:pointer}
.cbx-input button:hover{filter:brightness(1.05)}
@media (max-width:480px){.cbx-panel{width:92vw;height:60vh}}
</style>