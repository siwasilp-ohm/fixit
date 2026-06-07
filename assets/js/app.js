/* FixIt SPA — Main Application */
'use strict';

// ═══════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════
const STATUS = {
  pending:          { label:'รอประเมิน',        badge:'badge-pending',     icon:'fa-clock',           color:'#FF9800' },
  estimating:       { label:'กำลังประเมินราคา',  badge:'badge-estimating',  icon:'fa-magnifying-glass', color:'#2196F3' },
  waiting_approval: { label:'รออนุมัติ',         badge:'badge-waiting',     icon:'fa-hourglass-half',  color:'#9C27B0' },
  approved:         { label:'อนุมัติแล้ว',       badge:'badge-approved',    icon:'fa-circle-check',    color:'#00BCD4' },
  in_progress:      { label:'กำลังซ่อม',         badge:'badge-in_progress', icon:'fa-screwdriver-wrench','color':'#FF5722' },
  completed:        { label:'เสร็จสิ้น',          badge:'badge-completed',   icon:'fa-check-double',    color:'#4CAF50' },
  cancelled:        { label:'ยกเลิก',            badge:'badge-cancelled',   icon:'fa-ban',             color:'#9E9E9E' },
};
const PRIORITY = {
  low:    { label:'ต่ำ',    badge:'badge-priority-low' },
  normal: { label:'ปกติ',   badge:'badge-priority-normal' },
  high:   { label:'สูง',    badge:'badge-priority-high' },
  urgent: { label:'เร่งด่วน',badge:'badge-priority-urgent' },
};
const ROLES = {
  admin:      'ผู้ดูแลระบบ', reporter:'ผู้แจ้งซ่อม',
  officer:    'เจ้าหน้าที่', technician:'ช่างซ่อม', director:'ผู้อำนวยการ'
};
const THEMES = [
  { name:'Blue',   color:'#2196F3' }, { name:'Indigo', color:'#3F51B5' },
  { name:'Purple', color:'#9C27B0' }, { name:'Teal',   color:'#009688' },
  { name:'Green',  color:'#4CAF50' }, { name:'Orange', color:'#FF9800' },
  { name:'Red',    color:'#F44336' }, { name:'Pink',   color:'#E91E63' },
  { name:'Cyan',   color:'#00BCD4' }, { name:'Brown',  color:'#795548' },
];
const ICONS = [
  'fa-wrench','fa-bolt','fa-droplet','fa-computer','fa-snowflake','fa-building',
  'fa-car','fa-gears','fa-plug','fa-hammer','fa-screwdriver','fa-fire',
  'fa-phone','fa-tv','fa-print','fa-wifi','fa-server','fa-lightbulb',
  'fa-fan','fa-elevator','fa-toilet','fa-sink','fa-toolbox','fa-circle-question'
];

let _user  = window.CURRENT_USER || {};
let _cats  = [];
let _techs = [];

// ═══════════════════════════════════════════════
// API
// ═══════════════════════════════════════════════
const API = {
  async req(method, endpoint, data = null) {
    UI.showLoading();
    try {
      const opts = { method, headers: {} };
      if (data && !(data instanceof FormData)) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
      } else if (data instanceof FormData) {
        opts.body = data;
      }
      const r = await fetch(endpoint, opts);
      if (r.status === 401) { location.href = 'index.php'; return null; }
      return await r.json();
    } catch (e) {
      UI.toast('error','ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
      return null;
    } finally { UI.hideLoading(); }
  },
  get:    (ep)     => API.req('GET', ep),
  post:   (ep, d)  => API.req('POST', ep, d),
  put:    (ep, d)  => API.req('PUT', ep, d),
  delete: (ep)     => API.req('DELETE', ep),
};

// ═══════════════════════════════════════════════
// THEME
// ═══════════════════════════════════════════════
const Theme = {
  apply(color) {
    const r = document.documentElement;
    r.style.setProperty('--primary', color);
    r.style.setProperty('--primary-dark', Theme._shade(color, -30));
    r.style.setProperty('--primary-light', Theme._tint(color, .88));
    r.style.setProperty('--primary-rgb', Theme._rgb(color));
    document.getElementById('meta-theme')?.setAttribute('content', color);
  },
  _shade(hex, amt) {
    let [r,g,b] = Theme._parse(hex);
    r = Math.max(0,Math.min(255,r+amt));
    g = Math.max(0,Math.min(255,g+amt));
    b = Math.max(0,Math.min(255,b+amt));
    return '#'+[r,g,b].map(v=>v.toString(16).padStart(2,'0')).join('');
  },
  _tint(hex, factor) {
    let [r,g,b] = Theme._parse(hex);
    r = Math.round(r+(255-r)*factor);
    g = Math.round(g+(255-g)*factor);
    b = Math.round(b+(255-b)*factor);
    return '#'+[r,g,b].map(v=>v.toString(16).padStart(2,'0')).join('');
  },
  _rgb(hex) { return Theme._parse(hex).join(','); },
  _parse(hex) {
    hex = hex.replace('#','');
    if (hex.length===3) hex = hex.split('').map(c=>c+c).join('');
    return [parseInt(hex.slice(0,2),16),parseInt(hex.slice(2,4),16),parseInt(hex.slice(4,6),16)];
  },
  async save(color) {
    await API.put('api/auth.php?action=theme', { theme_color: color });
    _user.theme_color = color;
    Theme.apply(color);
  }
};

// ═══════════════════════════════════════════════
// UI
// ═══════════════════════════════════════════════
const UI = {
  _loading: 0,
  showLoading() {
    this._loading++;
    document.getElementById('loading-overlay')?.classList.remove('hidden');
  },
  hideLoading() {
    this._loading = Math.max(0, this._loading - 1);
    if (this._loading === 0) document.getElementById('loading-overlay')?.classList.add('hidden');
  },
  modal: {
    show(title, body, footerBtns = [], cls = '') {
      const ov = document.getElementById('modal-overlay');
      const m  = document.getElementById('main-modal');
      document.getElementById('modal-title').textContent = title;
      document.getElementById('modal-body').innerHTML = body;
      const fEl = document.getElementById('modal-footer');
      fEl.innerHTML = footerBtns.join('');
      m.className = 'modal ' + cls;
      ov.classList.add('show');
    },
    hide(e) {
      if (e && e.target !== document.getElementById('modal-overlay')) return;
      document.getElementById('modal-overlay')?.classList.remove('show');
    },
    close() { document.getElementById('modal-overlay')?.classList.remove('show'); }
  },
  toast(type, msg, title = '') {
    let tc = document.querySelector('.toast-container');
    if (!tc) { tc = document.createElement('div'); tc.className='toast-container'; document.body.appendChild(tc); }
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
    const titles = { success:'สำเร็จ', error:'เกิดข้อผิดพลาด', warning:'คำเตือน', info:'ข้อมูล' };
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<i class="fa-solid ${icons[type]||'fa-info'} toast-icon"></i>
      <div class="toast-body">
        <div class="toast-title">${title||titles[type]}</div>
        <div class="toast-msg">${msg}</div>
      </div>`;
    tc.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 300); }, 3500);
  },
  confirm(msg, title='ยืนยันการดำเนินการ') {
    return Swal.fire({
      title, html: msg, icon:'warning',
      showCancelButton: true,
      confirmButtonText: '<i class="fa-solid fa-check"></i> ยืนยัน',
      cancelButtonText:  '<i class="fa-solid fa-xmark"></i> ยกเลิก',
      confirmButtonColor: '#f44336',
      cancelButtonColor:  '#9e9e9e',
      buttonsStyling: true,
    });
  },
  fmtDate(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('th-TH',{year:'numeric',month:'short',day:'numeric'});
  },
  fmtDateTime(d) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('th-TH',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
  },
  fmtMoney(n) {
    if (!n && n !== 0) return '-';
    return parseFloat(n).toLocaleString('th-TH', { style:'currency', currency:'THB' });
  },
  statusBadge(s) {
    const c = STATUS[s] || { label:s, badge:'badge-primary', icon:'fa-circle' };
    return `<span class="badge ${c.badge}"><i class="fa-solid ${c.icon}"></i> ${c.label}</span>`;
  },
  priorityBadge(p) {
    const c = PRIORITY[p] || { label:p, badge:'badge-primary' };
    return `<span class="badge ${c.badge}">${c.label}</span>`;
  },
  catBadge(name, color, icon) {
    return `<span class="badge" style="background:${color}22;color:${color}"><i class="fa-solid ${icon||'fa-tag'}"></i> ${name||'-'}</span>`;
  }
};

// ═══════════════════════════════════════════════
// ROUTER  (supports /path/id?key=val)
// ═══════════════════════════════════════════════
const Router = {
  routes: {},
  params: {},   // parsed query params from hash
  init() {
    window.addEventListener('hashchange', () => this._run());
    this._run();
  },
  add(hash, fn) { this.routes[hash] = fn; },
  go(hash) { location.hash = hash; },
  _run() {
    const raw = (location.hash.replace('#','') || 'dashboard');
    // Split path from query string
    const [pathPart, queryPart] = raw.split('?');
    const parts = pathPart.split('/');
    const key   = parts[0];
    // Parse query string into Router.params
    this.params = {};
    if (queryPart) {
      queryPart.split('&').forEach(kv => {
        const [k,v] = kv.split('=');
        if (k) this.params[decodeURIComponent(k)] = decodeURIComponent(v||'');
      });
    }
    const fn = this.routes[key] || this.routes['dashboard'];
    if (fn) fn(parts[1]);
    App.setActiveNav(key);
  }
};

// ═══════════════════════════════════════════════
// CHARTS
// ═══════════════════════════════════════════════
const Charts = { _doughnut: null, _bar: null };
function renderDoughnut(labels, data, colors) {
  const canvas = document.getElementById('chart-doughnut');
  if (!canvas) return;
  Charts._doughnut?.destroy();
  Charts._doughnut = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      plugins: {
        legend: { position:'right', labels:{ font:{size:11}, padding:10, boxWidth:12 } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} รายการ` } }
      }
    }
  });
}
function renderBar(labels, data) {
  const canvas = document.getElementById('chart-bar');
  if (!canvas) return;
  Charts._bar?.destroy();
  const color = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#2196F3';
  Charts._bar = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'จำนวนงานซ่อม', data,
        backgroundColor: color + '99', borderColor: color,
        borderWidth: 2, borderRadius: 6, borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display:false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw} รายการ` } } },
      scales: {
        y: { beginAtZero:true, ticks:{ stepSize:1 }, grid:{ color:'#f0f0f0' } },
        x: { grid:{ display:false } }
      }
    }
  });
}

// ═══════════════════════════════════════════════
// VIEW: DASHBOARD
// ═══════════════════════════════════════════════
async function viewDashboard() {
  document.getElementById('page-title').textContent = 'Dashboard';
  const pc = document.getElementById('page-content');
  pc.innerHTML = `
    <div class="stats-grid" id="stats-grid">
      ${[1,2,3,4,5].map(()=>`<div class="stat-card"><div class="stat-icon skeleton" style="width:52px;height:52px"></div><div><div class="skeleton" style="width:60px;height:28px;margin-bottom:6px"></div><div class="skeleton" style="width:80px;height:14px"></div></div></div>`).join('')}
    </div>
    <div class="chart-grid">
      <div class="chart-card"><h3><i class="fa-solid fa-chart-pie"></i> แยกตามหมวดหมู่</h3><div class="chart-wrapper"><canvas id="chart-doughnut"></canvas></div></div>
      <div class="chart-card"><h3><i class="fa-solid fa-chart-bar"></i> สถิติย้อนหลัง 6 เดือน</h3><div class="chart-wrapper"><canvas id="chart-bar"></canvas></div></div>
    </div>
    <div class="table-card">
      <div class="table-toolbar"><span class="fw-700"><i class="fa-solid fa-clock-rotate-left"></i> รายการล่าสุด</span></div>
      <div class="table-wrap"><table><thead><tr><th>เลขที่</th><th>อาการ/ปัญหา</th><th>หมวดหมู่</th><th>สถานะ</th><th>ผู้แจ้ง</th><th>วันที่</th></tr></thead><tbody id="recent-tbody"></tbody></table></div>
    </div>`;

  const res = await API.get('api/dashboard.php');
  if (!res?.success) return;
  const { counts, category_chart, monthly_chart, recent_repairs } = res;

  const statsConf = [
    { key:'total',            label:'ทั้งหมด',       icon:'fa-list-check',        bg:'#E3F2FD', color:'#1565C0' },
    { key:'pending',          label:'รอประเมิน',      icon:'fa-clock',             bg:'#FFF3E0', color:'#E65100' },
    { key:'waiting_approval', label:'รออนุมัติ',      icon:'fa-hourglass-half',    bg:'#F3E5F5', color:'#6A1B9A' },
    { key:'in_progress',      label:'กำลังซ่อม',      icon:'fa-screwdriver-wrench',bg:'#FBE9E7', color:'#BF360C' },
    { key:'completed',        label:'เสร็จสิ้น',       icon:'fa-check-double',      bg:'#E8F5E9', color:'#1B5E20' },
  ];
  document.getElementById('stats-grid').innerHTML = statsConf.map((s,i)=>`
    <div class="stat-card" style="animation-delay:${i*.07}s;cursor:pointer" onclick="Router.go('${s.key==='total'?'repairs':'repairs?status='+s.key}')">
      <div class="stat-icon" style="background:${s.bg};color:${s.color}"><i class="fa-solid ${s.icon}"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:${s.color}">${counts[s.key]||0}</div>
        <div class="stat-label">${s.label}</div>
      </div>
    </div>`).join('');

  if (category_chart?.length) {
    renderDoughnut(category_chart.map(r=>r.name||'ไม่ระบุ'), category_chart.map(r=>r.cnt), category_chart.map(r=>r.color||'#ccc'));
  }
  if (monthly_chart?.length) {
    renderBar(monthly_chart.map(r=>r.label), monthly_chart.map(r=>r.count));
  }

  const tbody = document.getElementById('recent-tbody');
  if (!recent_repairs?.length) { tbody.innerHTML = `<tr><td colspan="6" class="table-empty"><i class="fa-solid fa-inbox"></i><br>ยังไม่มีรายการ</td></tr>`; return; }
  tbody.innerHTML = recent_repairs.map(r=>`
    <tr style="cursor:pointer" onclick="Router.go('repair-detail/${r.id}')">
      <td><code style="font-size:12px;color:var(--primary)">${r.repair_number}</code></td>
      <td class="truncate" style="max-width:200px">${r.subject}</td>
      <td>${r.category_name ? UI.catBadge(r.category_name,r.category_color,'fa-tag') : '-'}</td>
      <td>${UI.statusBadge(r.status)}</td>
      <td>${r.reporter_name||'-'}</td>
      <td style="white-space:nowrap">${UI.fmtDate(r.created_at)}</td>
    </tr>`).join('');
}

// ═══════════════════════════════════════════════
// VIEW: USERS
// ═══════════════════════════════════════════════
async function viewUsers() {
  document.getElementById('page-title').textContent = 'จัดการผู้ใช้งาน';
  const pc = document.getElementById('page-content');
  pc.innerHTML = `
    <div class="section-header">
      <div><div class="section-title"><i class="fa-solid fa-users"></i> รายการผู้ใช้งาน</div></div>
      <button class="btn btn-primary" onclick="openUserForm()"><i class="fa-solid fa-plus"></i> เพิ่มผู้ใช้งาน</button>
    </div>
    <div class="table-card">
      <div class="table-toolbar">
        <div class="filter-bar">
          <div class="search-input-wrap"><i class="fa-solid fa-search"></i><input id="user-search" class="search-input" placeholder="ค้นหาชื่อ/username..."></div>
          <select id="user-role-filter" class="filter-select">
            <option value="">ทุกบทบาท</option>
            ${Object.entries(ROLES).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Username</th><th>ชื่อ-นามสกุล</th><th>บทบาท</th><th>หน่วยงาน</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
        <tbody id="user-tbody"></tbody>
      </table></div>
    </div>`;

  const load = async () => {
    const s = document.getElementById('user-search').value;
    const r = document.getElementById('user-role-filter').value;
    const res = await API.get(`api/users.php?search=${encodeURIComponent(s)}&role=${r}`);
    const tbody = document.getElementById('user-tbody');
    if (!res?.data?.length) { tbody.innerHTML=`<tr><td colspan="7" class="table-empty"><i class="fa-solid fa-user-slash"></i><br>ไม่พบผู้ใช้งาน</td></tr>`; return; }
    tbody.innerHTML = res.data.map((u,i)=>`
      <tr>
        <td style="color:var(--text-muted);font-size:12px">${i+1}</td>
        <td><code style="font-size:12.5px">${u.username}</code></td>
        <td>
          <div class="flex items-center gap-2">
            <div style="width:32px;height:32px;border-radius:50%;background:${u.theme_color||'#2196F3'};color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">${u.full_name.charAt(0)}</div>
            ${u.full_name}
          </div>
        </td>
        <td><span class="badge badge-primary">${ROLES[u.role]||u.role}</span></td>
        <td>${u.department||'-'}</td>
        <td>${u.active ? '<span class="badge badge-success">ใช้งาน</span>' : '<span class="badge badge-cancelled">ปิดใช้งาน</span>'}</td>
        <td>
          <div class="flex gap-2">
            <button class="btn btn-ghost btn-xs" onclick='openUserForm(${JSON.stringify(u)})'><i class="fa-solid fa-pen"></i></button>
            <button class="btn btn-danger btn-xs" onclick="deleteUser(${u.id},'${u.username}')"><i class="fa-solid fa-trash"></i></button>
          </div>
        </td>
      </tr>`).join('');
  };
  document.getElementById('user-search').addEventListener('input', debounce(load,400));
  document.getElementById('user-role-filter').addEventListener('change', load);
  load();
}

function openUserForm(u = null) {
  const isEdit = !!u;
  UI.modal.show(isEdit ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน', `
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label required">Username</label>
        <input id="uf-username" class="form-control" value="${u?.username||''}" ${isEdit?'disabled':''}>
      </div>
      <div class="form-group">
        <label class="form-label ${isEdit?'':'required'}">Password ${isEdit?'(เว้นว่างถ้าไม่เปลี่ยน)':''}</label>
        <input type="password" id="uf-password" class="form-control" placeholder="${isEdit?'เว้นว่างถ้าไม่เปลี่ยน':''}">
      </div>
      <div class="form-group full">
        <label class="form-label required">ชื่อ-นามสกุล</label>
        <input id="uf-fullname" class="form-control" value="${u?.full_name||''}">
      </div>
      <div class="form-group">
        <label class="form-label required">บทบาท</label>
        <select id="uf-role" class="form-control">
          ${Object.entries(ROLES).map(([k,v])=>`<option value="${k}" ${u?.role===k?'selected':''}>${v}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">หน่วยงาน</label>
        <input id="uf-dept" class="form-control" value="${u?.department||''}">
      </div>
      <div class="form-group">
        <label class="form-label">อีเมล</label>
        <input id="uf-email" class="form-control" value="${u?.email||''}">
      </div>
      <div class="form-group">
        <label class="form-label">เบอร์โทร</label>
        <input id="uf-phone" class="form-control" value="${u?.phone||''}">
      </div>
      ${isEdit?`<div class="form-group"><label class="form-label">สถานะ</label><select id="uf-active" class="form-control"><option value="1" ${u?.active?'selected':''}>ใช้งาน</option><option value="0" ${!u?.active?'selected':''}>ปิดใช้งาน</option></select></div>`:''}
    </div>`,
    [`<button class="btn btn-ghost" onclick="UI.modal.close()">ยกเลิก</button>`,
     `<button class="btn btn-primary" onclick="saveUser(${isEdit?u.id:'null'},'${u?.username||''}')">${isEdit?'บันทึก':'เพิ่มผู้ใช้งาน'}</button>`]
  );
}

async function saveUser(id, username) {
  const data = {
    username:   id ? username : document.getElementById('uf-username').value,
    password:   document.getElementById('uf-password').value,
    full_name:  document.getElementById('uf-fullname').value,
    role:       document.getElementById('uf-role').value,
    department: document.getElementById('uf-dept').value,
    email:      document.getElementById('uf-email').value,
    phone:      document.getElementById('uf-phone').value,
    active:     document.getElementById('uf-active')?.value ?? 1,
  };
  if (!data.full_name) { UI.toast('error','กรุณากรอกชื่อ-นามสกุล'); return; }
  const res = id ? await API.put(`api/users.php?id=${id}`, data) : await API.post('api/users.php', data);
  if (res?.success) { UI.modal.close(); UI.toast('success', res.message); viewUsers(); }
  else UI.toast('error', res?.message || 'เกิดข้อผิดพลาด');
}

async function deleteUser(id, uname) {
  const conf = await UI.confirm(`ต้องการลบผู้ใช้งาน <b>${uname}</b> ใช่หรือไม่?`);
  if (!conf.isConfirmed) return;
  const res = await API.delete(`api/users.php?id=${id}`);
  if (res?.success) { UI.toast('success', res.message); viewUsers(); }
  else UI.toast('error', res?.message);
}

// ═══════════════════════════════════════════════
// VIEW: CATEGORIES
// ═══════════════════════════════════════════════
async function viewCategories() {
  document.getElementById('page-title').textContent = 'จัดการหมวดหมู่';
  const pc = document.getElementById('page-content');
  pc.innerHTML = `
    <div class="section-header">
      <div class="section-title"><i class="fa-solid fa-tags"></i> หมวดหมู่งานซ่อม</div>
      <button class="btn btn-primary" onclick="openCatForm()"><i class="fa-solid fa-plus"></i> เพิ่มหมวดหมู่</button>
    </div>
    <div class="table-card">
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>ชื่อหมวดหมู่</th><th>สี</th><th>ไอคอน</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
        <tbody id="cat-tbody"></tbody>
      </table></div>
    </div>`;
  loadCategories();
}

async function loadCategories() {
  const res = await API.get('api/categories.php');
  _cats = res?.data || [];
  const tbody = document.getElementById('cat-tbody');
  if (!tbody) return;
  if (!_cats.length) { tbody.innerHTML=`<tr><td colspan="6" class="table-empty"><i class="fa-solid fa-tags"></i><br>ยังไม่มีหมวดหมู่</td></tr>`; return; }
  tbody.innerHTML = _cats.map((c,i)=>`
    <tr>
      <td style="color:var(--text-muted)">${i+1}</td>
      <td><div class="flex items-center gap-2"><div style="width:32px;height:32px;border-radius:8px;background:${c.color};display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px"><i class="fa-solid ${c.icon||'fa-tag'}"></i></div>${c.name}</div></td>
      <td><div style="width:28px;height:28px;border-radius:6px;background:${c.color};border:2px solid rgba(0,0,0,.1)"></div></td>
      <td><code>${c.icon}</code></td>
      <td>${c.active ? '<span class="badge badge-success">ใช้งาน</span>' : '<span class="badge badge-cancelled">ปิดใช้งาน</span>'}</td>
      <td><div class="flex gap-2">
        <button class="btn btn-ghost btn-xs" onclick='openCatForm(${JSON.stringify(c)})'><i class="fa-solid fa-pen"></i></button>
        <button class="btn btn-danger btn-xs" onclick="deleteCat(${c.id})"><i class="fa-solid fa-trash"></i></button>
      </div></td>
    </tr>`).join('');
}

function openCatForm(c = null) {
  const isEdit = !!c;
  UI.modal.show(isEdit ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่', `
    <div class="form-group mb-4">
      <label class="form-label required">ชื่อหมวดหมู่</label>
      <input id="cf-name" class="form-control" value="${c?.name||''}" placeholder="เช่น ระบบไฟฟ้า">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">สี</label>
        <input type="color" id="cf-color" class="form-control" value="${c?.color||'#2196F3'}" style="height:42px;padding:4px">
      </div>
      <div class="form-group">
        <label class="form-label">ไอคอน (FontAwesome)</label>
        <input id="cf-icon" class="form-control" value="${c?.icon||'fa-wrench'}" placeholder="fa-wrench" oninput="updateIconPreview()">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">เลือกไอคอน</label>
      <div class="icon-grid">${ICONS.map(ic=>`<div class="icon-option ${c?.icon===ic?'selected':''}" onclick="selectIcon('${ic}')" title="${ic}"><i class="fa-solid ${ic}"></i></div>`).join('')}</div>
    </div>`,
    [`<button class="btn btn-ghost" onclick="UI.modal.close()">ยกเลิก</button>`,
     `<button class="btn btn-primary" onclick="saveCat(${isEdit?c.id:'null'})">${isEdit?'บันทึก':'เพิ่มหมวดหมู่'}</button>`]
  );
}

function selectIcon(ic) {
  document.getElementById('cf-icon').value = ic;
  document.querySelectorAll('.icon-option').forEach(el => el.classList.toggle('selected', el.querySelector('i').className.includes(ic)));
}

async function saveCat(id) {
  const data = { name: document.getElementById('cf-name').value, color: document.getElementById('cf-color').value, icon: document.getElementById('cf-icon').value };
  if (!data.name) { UI.toast('error','กรุณากรอกชื่อหมวดหมู่'); return; }
  const res = id ? await API.put(`api/categories.php?id=${id}`, data) : await API.post('api/categories.php', data);
  if (res?.success) { UI.modal.close(); UI.toast('success', res.message); loadCategories(); }
  else UI.toast('error', res?.message);
}

async function deleteCat(id) {
  const conf = await UI.confirm('ต้องการลบหมวดหมู่นี้ใช่หรือไม่?');
  if (!conf.isConfirmed) return;
  const res = await API.delete(`api/categories.php?id=${id}`);
  if (res?.success) { UI.toast('success', res.message); loadCategories(); }
  else UI.toast('error', res?.message);
}

// ═══════════════════════════════════════════════
// VIEW: REPAIRS LIST
// ═══════════════════════════════════════════════
let repairPage = 1;
async function viewRepairs(forMine = false) {
  const isManager = ['admin','officer','director','technician'].includes(_user.role);
  const title = forMine ? 'รายการของฉัน' : 'รายการแจ้งซ่อมทั้งหมด';
  document.getElementById('page-title').textContent = title;
  const pc = document.getElementById('page-content');
  const canCreate = true;
  pc.innerHTML = `
    <div class="section-header">
      <div class="section-title"><i class="fa-solid fa-clipboard-list"></i> ${title}</div>
      <div class="flex gap-2">
        ${isManager?`<button class="btn btn-ghost btn-sm" onclick="window.open('api/reports.php?type=repairs','_blank')"><i class="fa-solid fa-print"></i> พิมพ์</button>`:''}
        ${canCreate?`<button class="btn btn-primary" onclick="openRepairForm()"><i class="fa-solid fa-plus"></i> แจ้งซ่อมใหม่</button>`:''}
      </div>
    </div>
    <div class="table-card">
      <div class="table-toolbar">
        <div class="filter-bar">
          <div class="search-input-wrap"><i class="fa-solid fa-search"></i><input id="rep-search" class="search-input" placeholder="เลขที่/อาการ..."></div>
          <select id="rep-status" class="filter-select"><option value="">ทุกสถานะ</option>${Object.entries(STATUS).map(([k,v])=>`<option value="${k}">${v.label}</option>`).join('')}</select>
          <select id="rep-cat" class="filter-select"><option value="">ทุกหมวดหมู่</option>${_cats.map(c=>`<option value="${c.id}">${c.name}</option>`).join('')}</select>
          <input type="date" id="rep-from" class="form-control" style="width:150px" title="จากวันที่">
          <input type="date" id="rep-to" class="form-control" style="width:150px" title="ถึงวันที่">
        </div>
      </div>
      <div class="table-wrap"><table>
        <thead><tr><th>เลขที่</th><th>วันที่แจ้ง</th><th>อาการ/ปัญหา</th><th>สถานที่</th><th>หมวดหมู่</th><th>สถานะ</th><th>ผู้แจ้ง</th><th>จัดการ</th></tr></thead>
        <tbody id="rep-tbody"></tbody>
      </table></div>
      <div class="table-footer"><span id="rep-count"></span><div class="pagination" id="rep-pagination"></div></div>
    </div>`;

  // Pre-fill status filter from Router.params (e.g. from dashboard card click)
  const preStatus = Router.params['status'] || '';
  if (preStatus) {
    const sel = document.getElementById('rep-status');
    if (sel) sel.value = preStatus;
    Router.params = {}; // consume
  }

  const load = async () => {
    const params = new URLSearchParams({
      search: document.getElementById('rep-search').value,
      status: document.getElementById('rep-status').value,
      category_id: document.getElementById('rep-cat').value,
      date_from: document.getElementById('rep-from').value,
      date_to: document.getElementById('rep-to').value,
      page: repairPage, limit: 15,
    });
    const res = await API.get(`api/repairs.php?${params}`);
    const tbody = document.getElementById('rep-tbody');
    if (!res?.data?.length) { tbody.innerHTML=`<tr><td colspan="8" class="table-empty"><i class="fa-solid fa-inbox"></i><br>ไม่พบรายการ</td></tr>`; document.getElementById('rep-count').textContent=''; document.getElementById('rep-pagination').innerHTML=''; return; }
    tbody.innerHTML = res.data.map(r=>`
      <tr style="cursor:pointer" onclick="Router.go('repair-detail/${r.id}')">
        <td><code style="color:var(--primary);font-size:12px">${r.repair_number}</code></td>
        <td style="white-space:nowrap;font-size:12.5px">${UI.fmtDate(r.created_at)}</td>
        <td class="truncate" style="max-width:200px">${r.subject}</td>
        <td style="font-size:12.5px">${r.building?`${r.building} ${r.room||''}`:r.location||'-'}</td>
        <td>${r.category_name ? UI.catBadge(r.category_name,r.category_color,r.category_icon) : '-'}</td>
        <td>${UI.statusBadge(r.status)}</td>
        <td style="font-size:12.5px">${r.reporter_name||'-'}</td>
        <td onclick="event.stopPropagation()">
          <div class="flex gap-1">
            <button class="btn btn-ghost btn-xs" onclick="Router.go('repair-detail/${r.id}')"><i class="fa-solid fa-eye"></i></button>
            ${isManager?`<button class="btn btn-primary btn-xs" onclick="openStatusModal(${r.id},'${r.status}')"><i class="fa-solid fa-arrow-right-arrow-left"></i></button>`:''}
            ${isManager?`<button class="btn btn-danger btn-xs" onclick="deleteRepair(${r.id})"><i class="fa-solid fa-trash"></i></button>`:''}
          </div>
        </td>
      </tr>`).join('');
    document.getElementById('rep-count').textContent = `แสดง ${res.data.length} จาก ${res.total} รายการ`;
    renderPagination('rep-pagination', res.total, res.page, res.limit, p => { repairPage=p; load(); });
  };
  ['rep-search','rep-status','rep-cat','rep-from','rep-to'].forEach(id => {
    const el = document.getElementById(id);
    el?.addEventListener('input', debounce(()=>{repairPage=1;load();},300));
    el?.addEventListener('change', ()=>{repairPage=1;load();});
  });
  repairPage = 1;
  load();
}

// ═══════════════════════════════════════════════
// VIEW: REPAIR DETAIL
// ═══════════════════════════════════════════════
async function viewRepairDetail(id) {
  document.getElementById('page-title').textContent = 'รายละเอียดการแจ้งซ่อม';
  const pc = document.getElementById('page-content');
  pc.innerHTML = `<div class="empty-state"><div class="empty-state-icon"><i class="fa-solid fa-spinner fa-spin"></i></div></div>`;

  const res = await API.get(`api/repairs.php?id=${id}`);
  if (!res?.data) { pc.innerHTML = `<div class="empty-state"><div class="empty-state-icon"><i class="fa-solid fa-circle-exclamation"></i></div><h3>ไม่พบรายการ</h3></div>`; return; }
  const r = res.data;
  const isManager = ['admin','officer','director','technician'].includes(_user.role);
  const canCancel = r.reporter_id == _user.id && r.status === 'pending';

  const timelineSteps = [
    { status:'pending',          label:'รับเรื่องแจ้งซ่อม',   icon:'fa-file-pen' },
    { status:'estimating',       label:'กำลังประเมินราคา',    icon:'fa-magnifying-glass-dollar' },
    { status:'waiting_approval', label:'รออนุมัติ',           icon:'fa-file-signature' },
    { status:'approved',         label:'อนุมัติแล้ว',         icon:'fa-check-circle' },
    { status:'in_progress',      label:'กำลังดำเนินการซ่อม',  icon:'fa-screwdriver-wrench' },
    { status:'completed',        label:'เสร็จสิ้น',            icon:'fa-check-double' },
  ];
  const statusOrder = timelineSteps.map(s=>s.status);
  const curIdx = r.status==='cancelled' ? -1 : statusOrder.indexOf(r.status);

  pc.innerHTML = `
    <div class="flex gap-2 mb-4">
      <button class="btn btn-ghost btn-sm" onclick="history.back()"><i class="fa-solid fa-arrow-left"></i> ย้อนกลับ</button>
      ${isManager?`<button class="btn btn-primary btn-sm" onclick="openStatusModal(${r.id},'${r.status}')"><i class="fa-solid fa-arrow-right-arrow-left"></i> เปลี่ยนสถานะ</button>`:''}
      ${canCancel?`<button class="btn btn-danger btn-sm" onclick="openStatusModal(${r.id},'${r.status}',true)"><i class="fa-solid fa-ban"></i> ยกเลิก</button>`:''}
      <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px" class="repair-detail-grid">
      <div>
        <!-- Main Info Card -->
        <div class="card mb-4">
          <div class="flex items-center justify-between mb-4">
            <div>
              <code style="font-size:16px;color:var(--primary);font-weight:700">${r.repair_number}</code>
              <span class="ml-2">${UI.statusBadge(r.status)}</span>
              <span class="ml-1">${UI.priorityBadge(r.priority)}</span>
            </div>
            <div style="font-size:12.5px;color:var(--text-muted)">${UI.fmtDateTime(r.created_at)}</div>
          </div>
          <h2 style="font-size:18px;font-weight:700;margin-bottom:16px">${r.subject}</h2>
          <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">หมวดหมู่</div><div class="detail-value">${r.category_name?UI.catBadge(r.category_name,r.category_color,r.category_icon):'-'}</div></div>
            <div class="detail-item"><div class="detail-label">สถานที่</div><div class="detail-value">${r.building?`${r.building} ${r.room||''}`:r.location||'-'}</div></div>
            <div class="detail-item"><div class="detail-label">ผู้แจ้งซ่อม</div><div class="detail-value">${r.reporter_name||'-'} <span style="color:var(--text-muted);font-size:12px">(${r.reporter_dept||'-'})</span></div></div>
            <div class="detail-item"><div class="detail-label">ช่างซ่อมที่รับผิดชอบ</div><div class="detail-value">${r.technician_name||'-'}</div></div>
            <div class="detail-item"><div class="detail-label">ราคาประเมิน</div><div class="detail-value" style="color:var(--warning);font-weight:600">${UI.fmtMoney(r.estimated_cost)}</div></div>
            <div class="detail-item"><div class="detail-label">ราคาจริง</div><div class="detail-value" style="color:var(--success);font-weight:600">${UI.fmtMoney(r.actual_cost)}</div></div>
            ${r.description?`<div class="detail-item full" style="grid-column:1/-1"><div class="detail-label">รายละเอียด</div><div class="detail-value">${r.description.replace(/\n/g,'<br>')}</div></div>`:''}
            ${r.notes?`<div class="detail-item full" style="grid-column:1/-1"><div class="detail-label">หมายเหตุ (เจ้าหน้าที่)</div><div class="detail-value">${r.notes.replace(/\n/g,'<br>')}</div></div>`:''}
          </div>
        </div>

        <!-- Images -->
        ${(r.images?.length)?`
        <div class="card mb-4">
          <div class="card-title mb-4"><i class="fa-solid fa-images"></i> รูปภาพ</div>
          ${['before','after'].map(type=>{
            const imgs = r.images.filter(i=>i.type===type);
            return imgs.length ? `<div class="mb-2"><div class="detail-label" style="margin-bottom:8px">${type==='before'?'ก่อนซ่อม':'หลังซ่อม'}</div><div class="img-gallery">${imgs.map(img=>`<img src="uploads/${img.filename}" class="img-thumb" onclick="openLightbox('uploads/${img.filename}')" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2290%22 height=%2290%22 viewBox=%220 0 90 90%22%3E%3Crect width=%2290%22 height=%2290%22 fill=%22%23f0f4f8%22/%3E%3Ctext x=%2245%22 y=%2255%22 font-size=%2228%22 text-anchor=%22middle%22 fill=%22%23aaa%22%3E🖼%3C/text%3E%3C/svg%3E'">`).join('')}</div></div>`:''
          }).join('')}
        </div>`:''}

        <!-- Upload Section -->
        ${isManager?`
        <div class="card mb-4">
          <div class="card-title mb-4"><i class="fa-solid fa-upload"></i> อัพโหลดรูปภาพ</div>
          <div class="flex gap-3 mb-3">
            <label><input type="radio" name="img-type" value="before" checked> ก่อนซ่อม</label>
            <label><input type="radio" name="img-type" value="after"> หลังซ่อม</label>
          </div>
          <div class="upload-zone" id="repair-upload-zone">
            <input type="file" accept="image/*" multiple onchange="handleRepairUpload(this,${r.id})">
            <div class="upload-zone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
            <div class="upload-zone-text">คลิกหรือลากไฟล์มาวางที่นี่</div>
            <div class="upload-zone-hint">รองรับ JPG, PNG, WEBP — ขนาดสูงสุด 50MB</div>
          </div>
          <div id="upload-preview-list" class="upload-preview-list"></div>
        </div>`:''}
      </div>

      <!-- Timeline Sidebar -->
      <div>
        <div class="card" style="position:sticky;top:80px">
          <div class="card-title mb-4"><i class="fa-solid fa-timeline"></i> ติดตามสถานะ</div>
          <div class="timeline" id="repair-timeline">
            ${r.status === 'cancelled' ? `
              <div class="timeline-item">
                <div class="timeline-dot-wrap"><div class="timeline-dot cancelled"><i class="fa-solid fa-ban"></i></div></div>
                <div class="timeline-content cancelled-step">
                  <div class="timeline-action">ยกเลิกรายการ</div>
                  ${r.history?.filter(h=>h.new_status==='cancelled').slice(-1).map(h=>`<div class="timeline-by">โดย: ${h.user_name||'-'}</div>${h.comment?`<div class="timeline-comment">${h.comment}</div>`:''}<div class="timeline-time">${UI.fmtDateTime(h.created_at)}</div>`).join('')||''}
                </div>
              </div>` :
              timelineSteps.map((step, idx) => {
                const isDone    = curIdx > idx;
                const isCurrent = curIdx === idx;
                const histItems = r.history?.filter(h=>h.new_status===step.status) || [];
                const lastHist  = histItems[histItems.length-1];
                return `
                  <div class="timeline-item">
                    <div class="timeline-dot-wrap">
                      <div class="timeline-dot ${isDone?'done':isCurrent?'current':''}">
                        ${isDone?`<i class="fa-solid fa-check"></i>`:isCurrent?`<i class="fa-solid fa-circle-dot"></i>`:''}
                      </div>
                    </div>
                    <div class="timeline-content ${isCurrent?'current-step':''}">
                      <div class="timeline-action"><i class="fa-solid ${step.icon}" style="color:${isDone?'var(--success)':isCurrent?'var(--primary)':'var(--text-muted)'}"></i> ${step.label}</div>
                      ${lastHist ? `
                        <div class="timeline-by">โดย: ${lastHist.user_name||'-'}</div>
                        ${lastHist.comment ? `<div class="timeline-comment">${lastHist.comment}</div>` : ''}
                        ${lastHist.cost ? `<div class="timeline-cost"><i class="fa-solid fa-baht-sign"></i> ${UI.fmtMoney(lastHist.cost)}</div>` : ''}
                        <div class="timeline-time"><i class="fa-regular fa-clock"></i> ${UI.fmtDateTime(lastHist.created_at)}</div>` :
                        (isCurrent ? `<div class="timeline-by" style="color:var(--primary)">สถานะปัจจุบัน</div>` :
                        `<div class="timeline-by" style="color:var(--text-light)">รอดำเนินการ</div>`)}
                    </div>
                  </div>`;
              }).join('')
            }
          </div>
        </div>
      </div>
    </div>`;

  // Responsive override for mobile
  const rGrid = pc.querySelector('.repair-detail-grid');
  if (window.innerWidth < 900 && rGrid) rGrid.style.gridTemplateColumns = '1fr';
}

function openLightbox(src) {
  const lb = document.getElementById('lightbox');
  document.getElementById('lightbox-img').src = src;
  lb.style.display = 'flex';
}

async function handleRepairUpload(input, repairId) {
  const files = Array.from(input.files);
  const imgType = document.querySelector('input[name="img-type"]:checked')?.value || 'before';
  const preview = document.getElementById('upload-preview-list');
  for (const file of files) {
    await uploadFileChunked(file, repairId, imgType, preview);
  }
  // Refresh page after uploads
  setTimeout(() => viewRepairDetail(repairId), 1000);
}

async function uploadFileChunked(file, repairId, imgType, previewEl) {
  const CHUNK = 1024 * 1024; // 1MB
  const totalChunks = Math.ceil(file.size / CHUNK);
  const uploadId = 'uid_' + Date.now() + '_' + Math.random().toString(36).slice(2);

  // Add preview item
  const itemId = 'prev_' + Date.now();
  const item = document.createElement('div');
  item.className = 'upload-preview-item'; item.id = itemId;
  const url = URL.createObjectURL(file);
  item.innerHTML = `<img src="${url}"><div class="upload-progress"><div class="upload-progress-bar" id="pb_${itemId}"></div></div>`;
  previewEl?.appendChild(item);

  for (let i = 0; i < totalChunks; i++) {
    const chunk = file.slice(i * CHUNK, (i + 1) * CHUNK);
    const fd = new FormData();
    fd.append('chunk', chunk, file.name);
    fd.append('upload_id', uploadId);
    fd.append('chunk_index', i);
    fd.append('total_chunks', totalChunks);
    fd.append('repair_id', repairId);
    fd.append('img_type', imgType);
    fd.append('original_name', file.name);

    try {
      const r = await fetch('api/upload.php?action=chunk', { method:'POST', body:fd });
      await r.json();
    } catch (e) { /* ignore */ }

    const pct = Math.round(((i+1) / totalChunks) * 100);
    const pb = document.getElementById('pb_' + itemId);
    if (pb) pb.style.width = pct + '%';
  }
}

// ═══════════════════════════════════════════════
// STATUS MODAL
// ═══════════════════════════════════════════════
function openStatusModal(id, currentStatus, cancelOnly = false) {
  const role = _user.role;
  const isAdmin   = ['admin','officer'].includes(role);
  const isDirector = role === 'director';
  const isTech    = role === 'technician';

  // Each role has a different set of allowed status transitions
  const managerNext = {
    pending:          ['estimating','cancelled'],
    estimating:       ['waiting_approval','cancelled'],
    waiting_approval: ['approved','cancelled'],
    approved:         ['in_progress','cancelled'],
    in_progress:      ['completed','cancelled'],
    completed: [], cancelled: [],
  };
  const directorNext = {
    waiting_approval: ['approved','cancelled'],
    approved:         ['in_progress'],
    pending:[], estimating:[], in_progress:[], completed:[], cancelled:[],
  };
  const techNext = {
    approved:    ['in_progress'],
    in_progress: ['completed'],
    pending:[], estimating:[], waiting_approval:[], completed:[], cancelled:[],
  };

  let available;
  if (cancelOnly) {
    available = ['cancelled'];
  } else if (isAdmin) {
    available = managerNext[currentStatus] || [];
  } else if (isDirector) {
    available = directorNext[currentStatus] || [];
  } else if (isTech) {
    available = techNext[currentStatus] || [];
  } else {
    available = ['cancelled']; // reporter can only cancel (handled separately)
  }

  if (!available.length) { UI.toast('info','ไม่สามารถเปลี่ยนสถานะได้ในขณะนี้'); return; }

  const showCost    = ['estimating','waiting_approval','completed'].includes(available[0]);
  const showAssign  = isAdmin && ['approved','in_progress'].includes(available[0]);

  UI.modal.show('เปลี่ยนสถานะรายการ', `
    <div class="form-group mb-4">
      <label class="form-label required">สถานะใหม่</label>
      <select id="new-status" class="form-control" onchange="onStatusSelectChange()">
        ${available.map(s=>`<option value="${s}">${STATUS[s]?.label||s}</option>`).join('')}
      </select>
    </div>
    <div class="form-group mb-4" id="cost-group" style="${showCost?'':'display:none'}">
      <label class="form-label">ค่าใช้จ่าย / ราคาประเมิน (บาท)</label>
      <input type="number" id="status-cost" class="form-control" min="0" step="0.01" placeholder="0.00">
    </div>
    ${showAssign ? `
    <div class="form-group mb-4" id="assign-group">
      <label class="form-label">มอบหมายช่างซ่อม</label>
      <select id="assign-tech" class="form-control">
        <option value="">-- ไม่ระบุ --</option>
        ${_techs.map(t=>`<option value="${t.id}">${t.full_name}</option>`).join('')}
      </select>
    </div>` : ''}
    <div class="form-group">
      <label class="form-label">หมายเหตุ / รายละเอียด</label>
      <textarea id="status-comment" class="form-control" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
    </div>`,
    [`<button class="btn btn-ghost" onclick="UI.modal.close()">ยกเลิก</button>`,
     `<button class="btn btn-primary" onclick="submitStatus(${id})"><i class="fa-solid fa-save"></i> บันทึกสถานะ</button>`]
  );
}

function onStatusSelectChange() {
  const val = document.getElementById('new-status')?.value;
  const costGroup = document.getElementById('cost-group');
  if (costGroup) costGroup.style.display = ['estimating','waiting_approval','completed'].includes(val) ? '' : 'none';
  const assignGroup = document.getElementById('assign-group');
  if (assignGroup) assignGroup.style.display = ['approved','in_progress'].includes(val) ? '' : 'none';
}

async function submitStatus(id) {
  const status  = document.getElementById('new-status').value;
  const comment = document.getElementById('status-comment').value;
  const cost    = document.getElementById('status-cost')?.value || '';
  const assign  = document.getElementById('assign-tech')?.value || '';
  const data = { status, comment, cost: cost||undefined, assigned_to: assign||undefined };
  const res = await API.put(`api/repairs.php?id=${id}&action=status`, data);
  if (res?.success) {
    UI.modal.close(); UI.toast('success', res.message);
    viewRepairDetail(id);
  } else UI.toast('error', res?.message);
}

// ═══════════════════════════════════════════════
// REPAIR FORM (New Request)
// ═══════════════════════════════════════════════
let _tempUploads = [];
let _assets = [];   // cached asset list for repair form

async function openRepairForm(prefillAssetId = '') {
  _tempUploads = [];
  // Fetch assets for the dropdown (only if not cached or small list)
  if (!_assets.length) {
    const res = await API.get('api/assets.php?status=active');
    _assets = res?.data || [];
  }
  const assetOptions = _assets.map(a=>`<option value="${a.id}" ${String(a.id)===String(prefillAssetId)?'selected':''}>${a.name} (${a.asset_code})</option>`).join('');

  UI.modal.show('แจ้งซ่อมใหม่', `
    <div class="form-group mb-4">
      <label class="form-label required">อาการ/ปัญหา</label>
      <input id="rf-subject" class="form-control" placeholder="อธิบายอาการเสียหรือปัญหาที่พบ" autofocus>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label required">หมวดหมู่</label>
        <select id="rf-cat" class="form-control">
          <option value="">-- เลือกหมวดหมู่ --</option>
          ${_cats.map(c=>`<option value="${c.id}">${c.name}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ความสำคัญ</label>
        <select id="rf-priority" class="form-control">
          ${Object.entries(PRIORITY).map(([k,v])=>`<option value="${k}" ${k==='normal'?'selected':''}>${v.label}</option>`).join('')}
        </select>
      </div>
      <div class="form-group full">
        <label class="form-label">เครื่อง/อุปกรณ์ที่เสีย <span class="text-muted">(ถ้ามี)</span></label>
        <select id="rf-asset" class="form-control">
          <option value="">-- ไม่ระบุ / เลือกอุปกรณ์ --</option>
          ${assetOptions}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">อาคาร</label>
        <input id="rf-building" class="form-control" placeholder="เช่น อาคาร A">
      </div>
      <div class="form-group">
        <label class="form-label">ห้อง/จุดที่พบปัญหา</label>
        <input id="rf-room" class="form-control" placeholder="เช่น A101">
      </div>
      <div class="form-group full">
        <label class="form-label">รายละเอียดเพิ่มเติม</label>
        <textarea id="rf-desc" class="form-control" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
      </div>
    </div>
    <div class="form-group mt-4">
      <label class="form-label">รูปภาพก่อนซ่อม</label>
      <div class="upload-zone" id="rf-upload-zone">
        <input type="file" accept="image/*" multiple onchange="handleTempUpload(this)">
        <div class="upload-zone-icon"><i class="fa-solid fa-camera"></i></div>
        <div class="upload-zone-text">คลิกหรือลากไฟล์มาวางที่นี่</div>
        <div class="upload-zone-hint">รองรับ JPG, PNG, WEBP — หลายไฟล์ได้</div>
      </div>
      <div id="rf-preview" class="upload-preview-list"></div>
    </div>`,
    [`<button class="btn btn-ghost" onclick="UI.modal.close()">ยกเลิก</button>`,
     `<button class="btn btn-primary" onclick="submitRepair()"><i class="fa-solid fa-paper-plane"></i> ส่งแจ้งซ่อม</button>`],
    'modal-lg'
  );
  // Auto-fill location when asset is selected
  document.getElementById('rf-asset')?.addEventListener('change', function() {
    const a = _assets.find(x=>String(x.id)===this.value);
    if (a) {
      if (a.building) document.getElementById('rf-building').value = a.building;
      if (a.room)     document.getElementById('rf-room').value = a.room;
    }
  });
  if (prefillAssetId) document.getElementById('rf-asset')?.dispatchEvent(new Event('change'));
}

function handleTempUpload(input) {
  const preview = document.getElementById('rf-preview');
  Array.from(input.files).forEach(file => {
    const url = URL.createObjectURL(file);
    const item = document.createElement('div');
    item.className = 'upload-preview-item';
    const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    item.dataset.tempId = tempId;
    item.innerHTML = `<img src="${url}"><button class="upload-remove" onclick="removeTempUpload('${tempId}',this)"><i class="fa-solid fa-xmark"></i></button>`;
    preview.appendChild(item);
    _tempUploads.push({ id: tempId, file });
  });
}
function removeTempUpload(tempId, btn) {
  _tempUploads = _tempUploads.filter(t => t.id !== tempId);
  btn.closest('.upload-preview-item').remove();
}

async function submitRepair() {
  const subject = document.getElementById('rf-subject').value.trim();
  if (!subject) { UI.toast('error','กรุณากรอกอาการ/ปัญหา'); return; }
  const data = {
    subject,
    category_id:  document.getElementById('rf-cat').value,
    asset_id:     document.getElementById('rf-asset')?.value || '',
    priority:     document.getElementById('rf-priority').value,
    building:     document.getElementById('rf-building').value,
    room:         document.getElementById('rf-room').value,
    description:  document.getElementById('rf-desc').value,
  };
  const res = await API.post('api/repairs.php', data);
  if (!res?.success) { UI.toast('error', res?.message); return; }
  const newId = res.id;

  // Upload temp files
  const preview = document.getElementById('rf-preview');
  for (const t of _tempUploads) {
    await uploadFileChunked(t.file, newId, 'before', preview);
  }

  UI.modal.close();
  UI.toast('success', `แจ้งซ่อมสำเร็จ! เลขที่ ${res.repair_number}`);
  Router.go('repair-detail/' + newId);
}

async function deleteRepair(id) {
  const conf = await UI.confirm('ต้องการลบรายการนี้ใช่หรือไม่?');
  if (!conf.isConfirmed) return;
  const res = await API.delete(`api/repairs.php?id=${id}`);
  if (res?.success) { UI.toast('success', res.message); viewRepairs(); }
  else UI.toast('error', res?.message);
}

// ═══════════════════════════════════════════════
// VIEW: ASSETS
// ═══════════════════════════════════════════════
async function viewAssets() {
  document.getElementById('page-title').textContent = 'จัดการอุปกรณ์/ทรัพย์สิน';
  const isManager = ['admin','officer'].includes(_user.role);
  const pc = document.getElementById('page-content');
  pc.innerHTML = `
    <div class="section-header">
      <div class="section-title"><i class="fa-solid fa-boxes-stacked"></i> รายการอุปกรณ์/ทรัพย์สิน</div>
      <div class="flex gap-2">
        <button class="btn btn-ghost btn-sm" onclick="toggleAssetView()" id="asset-view-btn"><i class="fa-solid fa-table-cells"></i> การ์ด</button>
        ${isManager?`<button class="btn btn-primary" onclick="openAssetForm()"><i class="fa-solid fa-plus"></i> เพิ่มอุปกรณ์</button>`:''}
      </div>
    </div>
    <div class="filter-bar mb-4">
      <div class="search-input-wrap"><i class="fa-solid fa-search"></i><input id="asset-search" class="search-input" placeholder="รหัส/ชื่ออุปกรณ์..."></div>
      <select id="asset-cat" class="filter-select"><option value="">ทุกหมวดหมู่</option>${_cats.map(c=>`<option value="${c.id}">${c.name}</option>`).join('')}</select>
      <select id="asset-status" class="filter-select"><option value="">ทุกสถานะ</option><option value="active">ใช้งาน</option><option value="maintenance">ซ่อมบำรุง</option><option value="retired">เลิกใช้</option></select>
    </div>
    <div id="asset-container"></div>`;

  let viewMode = 'card';
  window.toggleAssetView = () => {
    viewMode = viewMode==='card'?'table':'card';
    document.getElementById('asset-view-btn').innerHTML = viewMode==='card'?'<i class="fa-solid fa-table-cells"></i> การ์ด':'<i class="fa-solid fa-list"></i> ตาราง';
    loadAssets();
  };

  const loadAssets = async () => {
    const params = new URLSearchParams({ search: document.getElementById('asset-search').value, category: document.getElementById('asset-cat').value, status: document.getElementById('asset-status').value });
    const res = await API.get(`api/assets.php?${params}`);
    const assets = res?.data || [];
    const cont = document.getElementById('asset-container');
    if (!assets.length) { cont.innerHTML=`<div class="empty-state"><div class="empty-state-icon"><i class="fa-solid fa-box-open"></i></div><h3>ไม่พบอุปกรณ์</h3></div>`; return; }

    if (viewMode==='card') {
      cont.innerHTML = `<div class="asset-grid">${assets.map(a=>`
        <div class="asset-card" onclick="Router.go('asset-detail/${a.id}')">
          <div class="asset-card-header">
            <div class="asset-icon-wrap" style="background:${a.category_color||'#E3F2FD'}22;color:${a.category_color||'#2196F3'}">
              <i class="fa-solid fa-cube"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div class="asset-name truncate">${a.name}</div>
              <div class="asset-code">${a.asset_code}</div>
            </div>
            ${isManager?`<div class="flex gap-1" onclick="event.stopPropagation()">
              <button class="btn btn-ghost btn-xs" onclick="showQR(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-qrcode"></i></button>
              <button class="btn btn-ghost btn-xs" onclick="openAssetForm(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i></button>
            </div>`:''}</div>
          <div class="asset-meta">
            ${a.building?`<div class="asset-meta-row"><i class="fa-solid fa-location-dot"></i> ${a.building} ${a.floor||''} ${a.room||''}</div>`:''}
            ${a.category_name?`<div class="asset-meta-row"><i class="fa-solid fa-tag"></i> ${a.category_name}</div>`:''}
            <div class="asset-meta-row">
              <span class="badge ${a.status==='active'?'badge-success':a.status==='maintenance'?'badge-warning':'badge-cancelled'}" style="font-size:11px">
                ${a.status==='active'?'ใช้งาน':a.status==='maintenance'?'ซ่อมบำรุง':'เลิกใช้'}
              </span>
            </div>
          </div>
        </div>`).join('')}</div>`;
    } else {
      cont.innerHTML = `<div class="table-card"><div class="table-wrap"><table>
        <thead><tr><th>รหัส</th><th>ชื่ออุปกรณ์</th><th>หมวดหมู่</th><th>ตำแหน่ง</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
        <tbody>${assets.map(a=>`<tr style="cursor:pointer" onclick="Router.go('asset-detail/${a.id}')">
          <td><code style="font-size:12px">${a.asset_code}</code></td>
          <td>${a.name}</td>
          <td>${a.category_name?UI.catBadge(a.category_name,a.category_color,'fa-tag'):'-'}</td>
          <td style="font-size:12.5px">${a.building?`${a.building} ${a.room||''}`:'-'}</td>
          <td><span class="badge ${a.status==='active'?'badge-success':a.status==='maintenance'?'badge-warning':'badge-cancelled'}">${a.status==='active'?'ใช้งาน':a.status==='maintenance'?'ซ่อมบำรุง':'เลิกใช้'}</span></td>
          <td onclick="event.stopPropagation()"><div class="flex gap-1">
            <button class="btn btn-ghost btn-xs" onclick="showQR(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-qrcode"></i></button>
            ${isManager?`<button class="btn btn-ghost btn-xs" onclick="openAssetForm(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i></button>`:''}
            ${isManager?`<button class="btn btn-danger btn-xs" onclick="deleteAsset(${a.id})"><i class="fa-solid fa-trash"></i></button>`:''}
          </div></td>
        </tr>`).join('')}</tbody>
      </table></div></div>`;
    }
  };

  ['asset-search','asset-cat','asset-status'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', debounce(loadAssets,300));
    document.getElementById(id)?.addEventListener('change', loadAssets);
  });
  loadAssets();
}

function showQR(asset) {
  const qrUrl = location.origin + location.pathname.replace('app.php','') + `scan.php?code=${asset.asset_code}`;
  UI.modal.show(`QR Code — ${asset.name}`, `
    <div style="text-align:center">
      <div class="qr-wrapper" style="display:inline-block">
        <canvas id="qr-canvas"></canvas>
        <div class="qr-asset-name">${asset.name}</div>
        <div class="qr-asset-code">${asset.asset_code}</div>
        ${asset.building?`<div class="qr-location"><i class="fa-solid fa-location-dot"></i> ${asset.building} ${asset.floor||''} ${asset.room||''}</div>`:''}
      </div>
      <div class="mt-4">
        <div class="text-sm text-muted mb-2">URL: <code style="font-size:11px">${qrUrl}</code></div>
      </div>
    </div>`,
    [`<button class="btn btn-ghost" onclick="UI.modal.close()">ปิด</button>`,
     `<button class="btn btn-primary" onclick="printQR('${asset.asset_code}','${asset.name}')"><i class="fa-solid fa-print"></i> พิมพ์ QR</button>`]
  );
  setTimeout(() => {
    QRCode.toCanvas(document.getElementById('qr-canvas'), qrUrl, { width:180, margin:2 }, () => {});
  }, 100);
}

function printQR(code, name) {
  const qrUrl = location.origin + location.pathname.replace('app.php','') + `scan.php?code=${code}`;
  const win = window.open('','_blank','width=400,height=500');
  const canvas = document.getElementById('qr-canvas');
  const img = canvas.toDataURL('image/png');
  win.document.write(`<!DOCTYPE html><html><head><title>QR - ${name}</title><style>body{font-family:sans-serif;text-align:center;padding:24px}h2{font-size:16px;margin:12px 0 4px}p{font-size:12px;color:#666;margin:2px 0}</style></head>
  <body><img src="${img}" width="200"><h2>${name}</h2><p>${code}</p><p>${qrUrl}</p><script>window.onload=()=>window.print()<\/script></body></html>`);
  win.document.close();
}

async function openAssetForm(a = null) {
  const locRes = await API.get('api/assets.php?action=locations');
  const locs = locRes?.data || [];
  const isEdit = !!a;
  UI.modal.show(isEdit?'แก้ไขอุปกรณ์':'เพิ่มอุปกรณ์', `
    <div class="form-grid">
      <div class="form-group"><label class="form-label">รหัสอุปกรณ์</label><input id="af-code" class="form-control" value="${a?.asset_code||''}" placeholder="ระบบสร้างอัตโนมัติถ้าเว้นว่าง"></div>
      <div class="form-group"><label class="form-label required">ชื่ออุปกรณ์</label><input id="af-name" class="form-control" value="${a?.name||''}"></div>
      <div class="form-group"><label class="form-label">หมวดหมู่</label><select id="af-cat" class="form-control"><option value="">-- เลือกหมวดหมู่ --</option>${_cats.map(c=>`<option value="${c.id}" ${a?.category_id==c.id?'selected':''}>${c.name}</option>`).join('')}</select></div>
      <div class="form-group"><label class="form-label">ตำแหน่ง</label><select id="af-loc" class="form-control"><option value="">-- เลือกตำแหน่ง --</option>${locs.map(l=>`<option value="${l.id}" ${a?.location_id==l.id?'selected':''}>${l.building} ${l.floor} ${l.room}</option>`).join('')}</select></div>
      <div class="form-group"><label class="form-label">ยี่ห้อ</label><input id="af-brand" class="form-control" value="${a?.brand||''}"></div>
      <div class="form-group"><label class="form-label">รุ่น</label><input id="af-model" class="form-control" value="${a?.model||''}"></div>
      <div class="form-group"><label class="form-label">Serial No.</label><input id="af-serial" class="form-control" value="${a?.serial_number||''}"></div>
      <div class="form-group"><label class="form-label">สถานะ</label><select id="af-status" class="form-control"><option value="active" ${a?.status==='active'?'selected':''}>ใช้งาน</option><option value="maintenance" ${a?.status==='maintenance'?'selected':''}>ซ่อมบำรุง</option><option value="retired" ${a?.status==='retired'?'selected':''}>เลิกใช้</option></select></div>
      <div class="form-group"><label class="form-label">วันที่ซื้อ</label><input type="date" id="af-purchase" class="form-control" value="${a?.purchase_date||''}"></div>
      <div class="form-group"><label class="form-label">หมดประกัน</label><input type="date" id="af-warranty" class="form-control" value="${a?.warranty_expire||''}"></div>
      <div class="form-group full"><label class="form-label">รายละเอียด</label><textarea id="af-desc" class="form-control" rows="2">${a?.description||''}</textarea></div>
    </div>`,
    [`<button class="btn btn-ghost" onclick="UI.modal.close()">ยกเลิก</button>`,
     `<button class="btn btn-primary" onclick="saveAsset(${isEdit?a.id:'null'})">${isEdit?'บันทึก':'เพิ่มอุปกรณ์'}</button>`],
    'modal-lg'
  );
}

async function saveAsset(id) {
  const data = {
    asset_code: document.getElementById('af-code').value,
    name: document.getElementById('af-name').value,
    category_id: document.getElementById('af-cat').value,
    location_id: document.getElementById('af-loc').value,
    brand: document.getElementById('af-brand').value,
    model: document.getElementById('af-model').value,
    serial_number: document.getElementById('af-serial').value,
    status: document.getElementById('af-status').value,
    purchase_date: document.getElementById('af-purchase').value,
    warranty_expire: document.getElementById('af-warranty').value,
    description: document.getElementById('af-desc').value,
  };
  if (!data.name) { UI.toast('error','กรุณากรอกชื่ออุปกรณ์'); return; }
  const res = id ? await API.put(`api/assets.php?id=${id}`, data) : await API.post('api/assets.php', data);
  if (res?.success) { UI.modal.close(); UI.toast('success', res.message); viewAssets(); }
  else UI.toast('error', res?.message);
}

async function deleteAsset(id) {
  const conf = await UI.confirm('ต้องการลบอุปกรณ์นี้ใช่หรือไม่?');
  if (!conf.isConfirmed) return;
  const res = await API.delete(`api/assets.php?id=${id}`);
  if (res?.success) { UI.toast('success', res.message); viewAssets(); }
  else UI.toast('error', res?.message);
}

// ═══════════════════════════════════════════════
// VIEW: REPORTS
// ═══════════════════════════════════════════════
async function viewReports() {
  document.getElementById('page-title').textContent = 'รายงานสรุป';
  const pc = document.getElementById('page-content');
  const today = new Date();
  const from = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-01';
  const to   = today.toISOString().slice(0,10);

  pc.innerHTML = `
    <div class="section-header">
      <div class="section-title"><i class="fa-solid fa-chart-line"></i> รายงานสรุป</div>
      <div class="flex gap-2 items-center">
        <input type="date" id="rpt-from" class="form-control" value="${from}" style="width:150px">
        <span>ถึง</span>
        <input type="date" id="rpt-to" class="form-control" value="${to}" style="width:150px">
        <button class="btn btn-primary" onclick="loadReport()"><i class="fa-solid fa-search"></i> ดูรายงาน</button>
        <button class="btn btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> พิมพ์</button>
      </div>
    </div>
    <div id="report-content"></div>`;

  window.loadReport = async () => {
    const f = document.getElementById('rpt-from').value;
    const t = document.getElementById('rpt-to').value;
    const res = await API.get(`api/reports.php?type=summary&date_from=${f}&date_to=${t}`);
    if (!res?.success) return;
    const { by_status, by_category, by_technician } = res;

    const total = by_status.reduce((s,r)=>(+r.cnt)+s,0);
    const totalCost = by_status.reduce((s,r)=>+(r.total_cost||0)+s,0);
    const completed = by_status.find(r=>r.status==='completed');

    document.getElementById('report-content').innerHTML = `
      <div class="report-grid mb-4">
        <div class="report-metric-card"><div class="report-metric-value">${total}</div><div class="report-metric-label">รายการทั้งหมด</div></div>
        <div class="report-metric-card"><div class="report-metric-value" style="color:var(--success)">${completed?.cnt||0}</div><div class="report-metric-label">เสร็จสิ้น</div></div>
        <div class="report-metric-card"><div class="report-metric-value" style="color:var(--warning)">${UI.fmtMoney(totalCost)}</div><div class="report-metric-label">ค่าใช้จ่ายรวม</div></div>
        <div class="report-metric-card"><div class="report-metric-value" style="color:var(--purple)">${total?Math.round((+(completed?.cnt||0)/total)*100)+'%':'0%'}</div><div class="report-metric-label">อัตราเสร็จสิ้น</div></div>
      </div>
      <div class="chart-grid mb-4">
        <div class="chart-card"><h3><i class="fa-solid fa-chart-pie"></i> แยกตามหมวดหมู่</h3><div class="chart-wrapper"><canvas id="rpt-doughnut"></canvas></div></div>
        <div class="chart-card"><h3><i class="fa-solid fa-chart-bar"></i> แยกตามสถานะ</h3><div class="chart-wrapper"><canvas id="rpt-bar"></canvas></div></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div class="table-card">
          <div class="table-toolbar"><span class="fw-700">แยกตามสถานะ</span></div>
          <div class="table-wrap"><table><thead><tr><th>สถานะ</th><th>จำนวน</th><th>ค่าใช้จ่าย</th></tr></thead>
          <tbody>${by_status.map(r=>`<tr><td>${UI.statusBadge(r.status)}</td><td><b>${r.cnt}</b></td><td>${UI.fmtMoney(r.total_cost)}</td></tr>`).join('')||'<tr><td colspan="3" class="table-empty">ไม่มีข้อมูล</td></tr>'}</tbody>
          </table></div>
        </div>
        <div class="table-card">
          <div class="table-toolbar"><span class="fw-700">ช่างซ่อม</span></div>
          <div class="table-wrap"><table><thead><tr><th>ชื่อ</th><th>รับงาน</th><th>เสร็จ</th><th>ค่าใช้จ่าย</th></tr></thead>
          <tbody>${by_technician.map(r=>`<tr><td>${r.full_name||'-'}</td><td>${r.cnt}</td><td>${r.completed}</td><td>${UI.fmtMoney(r.total_cost)}</td></tr>`).join('')||'<tr><td colspan="4" class="table-empty">ไม่มีข้อมูล</td></tr>'}</tbody>
          </table></div>
        </div>
      </div>`;

    if (by_category.length) {
      setTimeout(() => {
        const ctx1 = document.getElementById('rpt-doughnut');
        if (ctx1) new Chart(ctx1, { type:'doughnut', data:{ labels:by_category.map(r=>r.name||'ไม่ระบุ'), datasets:[{ data:by_category.map(r=>r.cnt), backgroundColor:by_category.map(r=>r.color||'#ccc'), borderWidth:2, borderColor:'#fff' }] }, options:{ responsive:true, maintainAspectRatio:false, cutout:'60%', plugins:{ legend:{position:'right',labels:{font:{size:11},padding:8,boxWidth:12}} } } });
        const ctx2 = document.getElementById('rpt-bar');
        if (ctx2) {
          const color = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
          new Chart(ctx2, { type:'bar', data:{ labels:by_status.map(r=>STATUS[r.status]?.label||r.status), datasets:[{ data:by_status.map(r=>r.cnt), backgroundColor:by_status.map(r=>STATUS[r.status]?.color+'99'||'#ccc'), borderColor:by_status.map(r=>STATUS[r.status]?.color||'#ccc'), borderWidth:2, borderRadius:6 }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ y:{beginAtZero:true,ticks:{stepSize:1}}, x:{grid:{display:false}} } } });
        }
      }, 100);
    }
  };
  loadReport();
}

// ═══════════════════════════════════════════════
// VIEW: SETTINGS
// ═══════════════════════════════════════════════
function viewSettings() {
  document.getElementById('page-title').textContent = 'การตั้งค่า';
  const pc = document.getElementById('page-content');
  pc.innerHTML = `
    <div style="max-width:560px">
      <div class="card mb-4">
        <div class="card-title mb-4"><i class="fa-solid fa-user-pen"></i> ข้อมูลส่วนตัว</div>
        <div class="form-group mb-4"><label class="form-label">ชื่อ-นามสกุล</label><input id="st-name" class="form-control" value="${_user.full_name||''}"></div>
        <div class="form-grid">
          <div class="form-group"><label class="form-label">อีเมล</label><input id="st-email" class="form-control" value="${_user.email||''}"></div>
          <div class="form-group"><label class="form-label">เบอร์โทร</label><input id="st-phone" class="form-control" value="${_user.phone||''}"></div>
        </div>
        <div class="form-group mt-4"><label class="form-label">รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label><input type="password" id="st-pw" class="form-control" placeholder="รหัสผ่านใหม่..."></div>
        <div class="mt-4"><button class="btn btn-primary" onclick="saveProfile()"><i class="fa-solid fa-save"></i> บันทึกข้อมูล</button></div>
      </div>

      <div class="card mb-4">
        <div class="card-title mb-4"><i class="fa-solid fa-palette"></i> ธีมสีของฉัน</div>
        <div class="theme-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px">
          ${THEMES.map(t=>`
            <div class="theme-dot ${_user.theme_color===t.color?'active':''}" style="background:${t.color};width:48px;height:48px;border-radius:50%;cursor:pointer;margin:auto;display:flex;align-items:center;justify-content:center;border:3px solid ${_user.theme_color===t.color?'#333':'transparent'};transition:.2s" onclick="applyTheme('${t.color}',this)" title="${t.name}">
              ${_user.theme_color===t.color?'<i class="fa-solid fa-check" style="color:#fff;font-size:16px"></i>':''}
            </div>`).join('')}
        </div>
        <div class="form-group">
          <label class="form-label">สีกำหนดเอง</label>
          <input type="color" id="custom-color" class="form-control" value="${_user.theme_color||'#2196F3'}" style="height:42px;padding:4px" oninput="applyThemeCustom(this.value)">
        </div>
      </div>

      <div class="card">
        <div class="card-title mb-2"><i class="fa-solid fa-circle-info"></i> ข้อมูลบัญชี</div>
        <div class="detail-grid">
          <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value"><code>${_user.username||'-'}</code></div></div>
          <div class="detail-item"><div class="detail-label">บทบาท</div><div class="detail-value"><span class="badge badge-primary">${ROLES[_user.role]||_user.role}</span></div></div>
          <div class="detail-item"><div class="detail-label">หน่วยงาน</div><div class="detail-value">${_user.department||'-'}</div></div>
        </div>
      </div>
    </div>`;

  window.applyTheme = (color, el) => {
    document.querySelectorAll('.theme-dot').forEach(d => { d.style.border='3px solid transparent'; d.innerHTML=''; });
    el.style.border = '3px solid #333'; el.innerHTML = '<i class="fa-solid fa-check" style="color:#fff;font-size:16px"></i>';
    Theme.save(color);
    document.getElementById('custom-color').value = color;
    App.renderSidebar();
  };
  window.applyThemeCustom = debounce((color) => { Theme.save(color); App.renderSidebar(); }, 500);
}

async function saveProfile() {
  const data = {
    full_name: document.getElementById('st-name').value,
    email:     document.getElementById('st-email').value,
    phone:     document.getElementById('st-phone').value,
    password:  document.getElementById('st-pw').value,
  };
  const res = await API.put('api/auth.php?action=profile', data);
  if (res?.success) {
    _user = { ..._user, ...res.user };
    UI.toast('success','บันทึกข้อมูลสำเร็จ');
    App.renderSidebar();
  } else UI.toast('error', res?.message);
}

// ═══════════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════════
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function renderPagination(containerId, total, page, limit, onPage) {
  const pages = Math.ceil(total / limit);
  if (pages <= 1) { document.getElementById(containerId).innerHTML=''; return; }
  const el = document.getElementById(containerId);
  let html = '';
  if (page > 1) html += `<button class="page-btn" onclick="(${onPage})(${page-1})"><i class="fa-solid fa-chevron-left"></i></button>`;
  for (let i = Math.max(1,page-2); i <= Math.min(pages,page+2); i++) {
    html += `<button class="page-btn ${i===page?'active':''}" onclick="(${onPage})(${i})">${i}</button>`;
  }
  if (page < pages) html += `<button class="page-btn" onclick="(${onPage})(${page+1})"><i class="fa-solid fa-chevron-right"></i></button>`;
  el.innerHTML = html;
}

// ═══════════════════════════════════════════════
// APP INIT
// ═══════════════════════════════════════════════
const App = {
  async init() {
    // Apply theme
    Theme.apply(_user.theme_color || '#2196F3');

    // Render sidebar
    this.renderSidebar();

    // Mobile sidebar toggle
    document.getElementById('menu-toggle').addEventListener('click', () => {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebar-overlay').classList.toggle('show');
    });
    document.getElementById('sidebar-close').addEventListener('click', () => {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sidebar-overlay').classList.remove('show');
    });
    document.getElementById('sidebar-overlay').addEventListener('click', () => {
      document.getElementById('sidebar').classList.remove('open');
      document.getElementById('sidebar-overlay').classList.remove('show');
    });

    // Theme popover
    document.getElementById('topbar-theme-btn').addEventListener('click', (e) => {
      e.stopPropagation();
      const pop = document.getElementById('theme-popover');
      if (pop.style.display === 'none' || !pop.style.display) {
        pop.style.display = 'block';
        pop.innerHTML = `<h4><i class="fa-solid fa-palette"></i> เลือกธีมสี</h4>
          <div class="theme-grid">${THEMES.map(t=>`<div class="theme-dot" style="background:${t.color}" onclick="Theme.save('${t.color}');document.getElementById('theme-popover').style.display='none';App.renderSidebar()" title="${t.name}"><i class="fa-solid fa-check"></i></div>`).join('')}</div>
          <div class="theme-custom"><label>สีกำหนดเอง</label><input type="color" value="${_user.theme_color||'#2196F3'}" oninput="debounce(c=>Theme.save(c),400)(this.value)"></div>`;
      } else {
        pop.style.display = 'none';
      }
    });
    document.addEventListener('click', () => { document.getElementById('theme-popover').style.display = 'none'; });

    // Load categories and technicians in parallel
    const [catRes, techRes] = await Promise.all([
      API.get('api/categories.php'),
      API.get('api/users.php?role=technician'),
    ]);
    _cats  = catRes?.data  || [];
    _techs = techRes?.data || [];

    // Load pending count for badge (non-blocking)
    App.refreshPendingBadge();

    // Register routes
    Router.add('dashboard',     () => viewDashboard());
    Router.add('repairs',       () => viewRepairs(false));
    Router.add('my-repairs',    () => viewRepairs(true));
    Router.add('repair-detail', (id) => viewRepairDetail(id));
    Router.add('users',         () => viewUsers());
    Router.add('categories',    () => viewCategories());
    Router.add('assets',        () => viewAssets());
    Router.add('asset-detail',  (id) => viewAssetDetail(id));
    Router.add('reports',       () => viewReports());
    Router.add('settings',      () => viewSettings());
    Router.add('new-repair',    () => { viewRepairs(false); setTimeout(()=>openRepairForm(),400); });

    Router.init();
    UI.hideLoading();
  },

  renderSidebar() {
    const u = _user;
    const initials = (u.full_name||'?').charAt(0).toUpperCase();
    const sbAvatar = document.getElementById('sb-avatar');
    if (sbAvatar) { sbAvatar.textContent = initials; sbAvatar.style.background = u.theme_color||'rgba(255,255,255,.25)'; }
    document.getElementById('sb-name').textContent  = u.full_name || u.username;
    document.getElementById('sb-role').textContent  = ROLES[u.role] || u.role;
    const topAvatar = document.getElementById('topbar-avatar');
    if (topAvatar) { topAvatar.textContent = initials; topAvatar.style.background = u.theme_color||'var(--primary)'; }
    topAvatar?.addEventListener('click', () => Router.go('settings'));

    const isManager = ['admin','officer'].includes(u.role);
    const isDirector = u.role === 'director';
    const isTech = u.role === 'technician';

    const nav = document.getElementById('sidebar-nav');
    const menu = [
      { section:'หลัก' },
      { hash:'dashboard', icon:'fa-gauge-high', label:'แดชบอร์ด', all:true },
      ...(isManager||isDirector ? [
        { section:'จัดการงานซ่อม' },
        { hash:'repairs', icon:'fa-clipboard-list', label:'รายการแจ้งซ่อม' },
        { hash:'assets', icon:'fa-boxes-stacked', label:'อุปกรณ์/ทรัพย์สิน' },
      ] : []),
      ...(isTech ? [
        { section:'งานของฉัน' },
        { hash:'repairs', icon:'fa-clipboard-list', label:'งานที่ได้รับมอบหมาย' },
      ] : []),
      ...(!isManager && !isDirector && !isTech ? [
        { section:'งานของฉัน' },
        { hash:'new-repair', icon:'fa-plus-circle', label:'แจ้งซ่อมใหม่' },
        { hash:'my-repairs', icon:'fa-list-check', label:'ติดตามสถานะ' },
      ] : []),
      ...(isManager ? [
        { section:'ผู้ดูแลระบบ' },
        { hash:'users', icon:'fa-users', label:'จัดการผู้ใช้งาน' },
        { hash:'categories', icon:'fa-tags', label:'หมวดหมู่งาน' },
      ] : []),
      ...((isManager||isDirector) ? [{ hash:'reports', icon:'fa-chart-line', label:'รายงาน' }] : []),
      { section:'ทั่วไป' },
      { hash:'settings', icon:'fa-gear', label:'การตั้งค่า' },
    ];

    nav.innerHTML = menu.map(m => m.section
      ? `<div class="nav-section-label">${m.section}</div>`
      : `<div class="nav-item" data-hash="${m.hash}" onclick="Router.go('${m.hash}');if(window.innerWidth<768){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebar-overlay').classList.remove('show')}">
          <i class="fa-solid ${m.icon} nav-icon"></i> <span>${m.label}</span>
        </div>`
    ).join('');
  },

  setActiveNav(hash) {
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.toggle('active', el.dataset.hash === hash);
    });
  },

  async refreshPendingBadge() {
    // Fetch dashboard counts silently (no loading overlay)
    try {
      const res = await fetch('api/dashboard.php');
      if (!res.ok) return;
      const json = await res.json();
      const pending = (json.counts?.pending || 0) + (json.counts?.waiting_approval || 0);
      // Update any nav-item that shows repairs
      document.querySelectorAll('.nav-item[data-hash="repairs"]').forEach(el => {
        let badge = el.querySelector('.nav-pending');
        if (pending > 0) {
          if (!badge) { badge = document.createElement('span'); badge.className='nav-pending'; el.appendChild(badge); }
          badge.textContent = pending > 99 ? '99+' : pending;
        } else if (badge) {
          badge.remove();
        }
      });
    } catch { /* silent */ }
  },

  async logout() {
    const conf = await UI.confirm('ต้องการออกจากระบบใช่หรือไม่?', 'ออกจากระบบ');
    if (!conf.isConfirmed) return;
    await API.post('api/auth.php?action=logout', {});
    location.href = 'index.php';
  }
};

// Asset Detail stub (same pattern as repair detail)
async function viewAssetDetail(id) {
  document.getElementById('page-title').textContent = 'รายละเอียดอุปกรณ์';
  const pc = document.getElementById('page-content');
  const res = await API.get(`api/assets.php?id=${id}`);
  if (!res?.data) { pc.innerHTML=`<div class="empty-state"><div class="empty-state-icon"><i class="fa-solid fa-circle-exclamation"></i></div><h3>ไม่พบข้อมูล</h3></div>`; return; }
  const a = res.data;
  const isManager = ['admin','officer'].includes(_user.role);
  pc.innerHTML = `
    <div class="flex gap-2 mb-4">
      <button class="btn btn-ghost btn-sm" onclick="history.back()"><i class="fa-solid fa-arrow-left"></i> ย้อนกลับ</button>
      <button class="btn btn-ghost btn-sm" onclick="showQR(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-qrcode"></i> QR Code</button>
      ${isManager?`<button class="btn btn-primary btn-sm" onclick="openAssetForm(${JSON.stringify(a).replace(/"/g,'&quot;')})"><i class="fa-solid fa-pen"></i> แก้ไข</button>`:''}
    </div>
    <div style="display:grid;grid-template-columns:1fr 360px;gap:20px">
      <div class="card">
        <div class="flex items-center gap-3 mb-4">
          <div style="width:56px;height:56px;border-radius:14px;background:${a.category_color||'#E3F2FD'}22;color:${a.category_color||'#2196F3'};display:flex;align-items:center;justify-content:center;font-size:24px"><i class="fa-solid fa-cube"></i></div>
          <div><h2 style="font-size:18px;font-weight:700">${a.name}</h2><code style="color:var(--text-muted)">${a.asset_code}</code></div>
          <span class="badge ${a.status==='active'?'badge-success':a.status==='maintenance'?'badge-warning':'badge-cancelled'}" style="margin-left:auto">${a.status==='active'?'ใช้งาน':a.status==='maintenance'?'ซ่อมบำรุง':'เลิกใช้'}</span>
        </div>
        <div class="detail-grid">
          <div class="detail-item"><div class="detail-label">หมวดหมู่</div><div class="detail-value">${a.category_name?UI.catBadge(a.category_name,a.category_color,'fa-tag'):'-'}</div></div>
          <div class="detail-item"><div class="detail-label">ตำแหน่ง</div><div class="detail-value">${a.building?`${a.building} ชั้น ${a.floor||'-'} ${a.room||''}`:'-'}</div></div>
          <div class="detail-item"><div class="detail-label">ยี่ห้อ/รุ่น</div><div class="detail-value">${a.brand||'-'} ${a.model?'/ '+a.model:''}</div></div>
          <div class="detail-item"><div class="detail-label">Serial No.</div><div class="detail-value"><code>${a.serial_number||'-'}</code></div></div>
          <div class="detail-item"><div class="detail-label">วันที่ซื้อ</div><div class="detail-value">${UI.fmtDate(a.purchase_date)}</div></div>
          <div class="detail-item"><div class="detail-label">หมดประกัน</div><div class="detail-value">${UI.fmtDate(a.warranty_expire)}</div></div>
          ${a.description?`<div class="detail-item" style="grid-column:1/-1"><div class="detail-label">รายละเอียด</div><div class="detail-value">${a.description}</div></div>`:''}
        </div>
      </div>
      <div class="card" style="height:fit-content">
        <div class="card-title mb-4"><i class="fa-solid fa-clipboard-list"></i> ประวัติการแจ้งซ่อม (${a.repairs?.length||0})</div>
        ${(a.repairs?.length) ? a.repairs.map(r=>`
          <div style="padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:8px;cursor:pointer" onclick="Router.go('repair-detail/${r.id}')">
            <div class="flex items-center justify-between">
              <code style="font-size:11.5px;color:var(--primary)">${r.repair_number}</code>
              ${UI.statusBadge(r.status)}
            </div>
            <div style="font-size:13px;margin-top:4px">${r.subject}</div>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px">${UI.fmtDate(r.created_at)} • ${r.reporter_name||'-'}</div>
          </div>`).join('') : '<div class="text-center text-muted" style="padding:20px">ยังไม่มีประวัติการแจ้งซ่อม</div>'}
        <button class="btn btn-primary w-full mt-4" onclick="openRepairForm('${a.id}')">
          <i class="fa-solid fa-plus"></i> แจ้งซ่อมสำหรับอุปกรณ์นี้
        </button>
      </div>
    </div>`;
  if (window.innerWidth < 900) pc.querySelector('div[style*="grid-template-columns"]').style.gridTemplateColumns = '1fr';
}

// Start
document.addEventListener('DOMContentLoaded', () => App.init());

// Register SW
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js').catch(()=>{});
}
