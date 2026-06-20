const API_KEYS = {
  vehicles: '102022400230',
  schedules: '102022400033',
  maintenance: '102022400256',
};

const VEHICLE_STATUSES = ['Available', 'In-Use', 'Maintenance'];

const state = {
  vehicles: [],
  schedules: [],
  maintenance: [],
  sso: readSsoSession(),
};

const els = {
  addVehicleBtn: document.querySelector('#addVehicleBtn'),
  refreshBtn: document.querySelector('#refreshBtn'),
  resetBtn: document.querySelector('#resetBtn'),
  submitBtn: document.querySelector('#submitBtn'),
  headerLogoutBtn: document.querySelector('#headerLogoutBtn'),
  sessionPill: document.querySelector('#sessionPill'),
  form: document.querySelector('#dispatchForm'),
  formStatus: document.querySelector('#formStatus'),
  resultBox: document.querySelector('#resultBox'),
  vehicleId: document.querySelector('#vehicleId'),
  ssoForm: document.querySelector('#ssoForm'),
  ssoLoginBtn: document.querySelector('#ssoLoginBtn'),
  vehicleDialog: document.querySelector('#vehicleDialog'),
  vehicleEditForm: document.querySelector('#vehicleEditForm'),
  vehicleDialogTitle: document.querySelector('#vehicleDialogTitle'),
  cancelVehicleEditBtn: document.querySelector('#cancelVehicleEditBtn'),
  saveVehicleBtn: document.querySelector('#saveVehicleBtn'),
  availableCount: document.querySelector('#availableCount'),
  inUseCount: document.querySelector('#inUseCount'),
  maintenanceVehicleCount: document.querySelector('#maintenanceVehicleCount'),
  scheduleCount: document.querySelector('#scheduleCount'),
  vehicleRows: document.querySelector('#vehicleRows'),
  scheduleRows: document.querySelector('#scheduleRows'),
  maintenanceRows: document.querySelector('#maintenanceRows'),
};

function setDefaultDates() {
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  const nextDay = new Date(today);
  nextDay.setDate(today.getDate() + 2);

  els.form.elements.departure_date.value = toDateInput(tomorrow);
  els.form.elements.return_date.value = toDateInput(nextDay);
}

function toDateInput(date) {
  return date.toISOString().slice(0, 10);
}

function readSsoSession() {
  return null;
}

function saveSsoSession(session) {
  state.sso = session;

  renderSsoState();
  syncControlAvailability();
}

async function api(path, key, options = {}) {
  const authHeaders = state.sso?.token
    ? {
      'Authorization': `Bearer ${state.sso.token}`,
      'X-SSO-API-KEY': state.sso.apiKey,
    }
    : {};

  const response = await fetch(path, {
    ...options,
    headers: {
      'Accept': 'application/json',
      'X-IAE-KEY': key,
      ...authHeaders,
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.headers || {}),
    },
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = payload.message || `HTTP ${response.status}`;
    const error = new Error(message);
    error.payload = payload;
    throw error;
  }

  return payload;
}

async function loadAll(options = {}) {
  if (!options.silent) {
    setStatus('Memuat data', 'muted');
  }

  const [vehicles, schedules, maintenance] = await Promise.all([
    api('/api/v1/vehicles', API_KEYS.vehicles),
    api('/api/v1/schedules', API_KEYS.schedules),
    api('/api/v1/maintenance', API_KEYS.maintenance),
  ]);

  state.vehicles = vehicles.data || [];
  state.schedules = schedules.data || [];
  state.maintenance = maintenance.data || [];
  render();

  if (!options.silent) {
    setStatus('Siap', 'ok');
  }
}

function render() {
  renderSsoState();
  renderStats();
  renderVehicleSelect();
  renderVehicles();
  renderSchedules();
  renderMaintenance();
}

function renderSsoState() {
  if (state.sso?.token) {
    els.sessionPill.textContent = `Login aktif`;
    document.body.classList.add('app-ready');
    document.body.classList.remove('auth-required');
  } else {
    els.sessionPill.textContent = 'Belum login';
    document.body.classList.add('auth-required');
    document.body.classList.remove('app-ready');
  }
}

function renderStats() {
  els.availableCount.textContent = countVehicles('Available');
  els.inUseCount.textContent = countVehicles('In-Use');
  els.maintenanceVehicleCount.textContent = countVehicles('Maintenance');
  els.scheduleCount.textContent = state.schedules.length;
}

function countVehicles(status) {
  return state.vehicles.filter(vehicle => vehicle.status === status).length;
}

function getAvailableVehicles() {
  return state.vehicles.filter(vehicle => vehicle.status === 'Available');
}

function renderVehicleSelect() {
  const available = getAvailableVehicles();
  const selectedValue = els.vehicleId.value;

  els.vehicleId.innerHTML = available.length
    ? available.map(vehicle => (
      `<option value="${vehicle.id}">${vehicle.id} - ${escapeHtml(vehicle.plate_number)} - ${escapeHtml(vehicle.model)}</option>`
    )).join('')
    : '<option value="">Tidak ada kendaraan available</option>';

  if (available.some(vehicle => String(vehicle.id) === selectedValue)) {
    els.vehicleId.value = selectedValue;
  }

  syncControlAvailability();
}

function renderVehicles() {
  els.vehicleRows.innerHTML = state.vehicles.map(vehicle => `
    <tr>
      <td>${vehicle.id}</td>
      <td>${escapeHtml(vehicle.plate_number)}</td>
      <td>${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</td>
      <td>${statusBadge(vehicle.status)}</td>
      <td>
        <div class="status-edit">
          <select id="vehicle-status-${vehicle.id}" class="vehicle-action-control" data-testid="vehicle-status-select-${vehicle.id}" data-action="save-status" data-vehicle-id="${vehicle.id}">
            ${VEHICLE_STATUSES.map(status => (
              `<option value="${status}"${status === vehicle.status ? ' selected' : ''}>${status}</option>`
            )).join('')}
          </select>
        </div>
      </td>
      <td>
        <div class="row-actions">
          <button class="btn secondary small vehicle-action-control" type="button" data-action="edit-vehicle" data-vehicle-id="${vehicle.id}" data-testid="vehicle-edit-${vehicle.id}">Edit</button>
        </div>
      </td>
    </tr>
  `).join('');

  syncControlAvailability();
}

function renderSchedules() {
  els.scheduleRows.innerHTML = state.schedules.slice().reverse().map(schedule => `
    <tr>
      <td>${schedule.id}</td>
      <td>${schedule.vehicle_id}</td>
      <td>${schedule.driver_id}</td>
      <td>${escapeHtml(schedule.destination)}</td>
      <td>${escapeHtml(schedule.status)}</td>
    </tr>
  `).join('');
}

function renderMaintenance() {
  els.maintenanceRows.innerHTML = state.maintenance.slice().reverse().map(item => `
    <tr>
      <td>${item.id}</td>
      <td>${item.schedule_id || '-'}</td>
      <td>${escapeHtml(item.vehicle_id)}</td>
      <td>${formatCurrency(item.fuel_limit)}</td>
      <td>${escapeHtml(item.last_service_date)}</td>
      <td>${escapeHtml(item.operational_coupon || '-')}</td>
    </tr>
  `).join('');
}

function statusBadge(status) {
  const className = String(status).toLowerCase().replace(/\s+/g, '-');
  return `<span class="badge ${className}">${escapeHtml(status)}</span>`;
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function setStatus(text, type = 'muted') {
  els.formStatus.textContent = text;
  els.formStatus.className = `status-pill ${type}`;
}

function setBusy(isBusy) {
  state.isBusy = isBusy;
  syncControlAvailability();
}

function syncControlAvailability() {
  const isBusy = Boolean(state.isBusy);
  const hasAvailableVehicle = getAvailableVehicles().length > 0;
  const hasSsoSession = Boolean(state.sso?.token);

  els.addVehicleBtn.disabled = isBusy;
  els.refreshBtn.disabled = isBusy;
  els.vehicleId.disabled = isBusy || !hasAvailableVehicle;
  els.submitBtn.disabled = isBusy || !hasAvailableVehicle || !hasSsoSession;
  els.headerLogoutBtn.disabled = isBusy || !hasSsoSession;
  els.ssoLoginBtn.disabled = isBusy;

  els.form.querySelectorAll('input').forEach(input => {
    input.disabled = isBusy;
  });

  els.vehicleRows.querySelectorAll('.vehicle-action-control').forEach(control => {
    control.disabled = isBusy;
  });

  els.vehicleEditForm.querySelectorAll('input, select, textarea, button').forEach(control => {
    control.disabled = isBusy && control.id !== 'cancelVehicleEditBtn';
  });
}

async function submitDispatch(event) {
  event.preventDefault();

  if (!state.sso?.token) {
    setStatus('Token SSO kosong', 'error');
    els.resultBox.textContent = JSON.stringify({
      message: 'Pasang token SSO eksternal dulu agar dispatch dapat mengirim SOAP audit dan event RabbitMQ.',
    }, null, 2);
    return;
  }

  if (!els.vehicleId.value) {
    setStatus('Kendaraan kosong', 'error');
    els.resultBox.textContent = JSON.stringify({
      message: 'Tidak ada kendaraan berstatus Available. Tambahkan kendaraan demo atau refresh data kendaraan.',
    }, null, 2);
    return;
  }

  const formData = new FormData(els.form);
  const payload = Object.fromEntries(formData.entries());
  payload.vehicle_id = Number(payload.vehicle_id);
  payload.driver_id = Number(payload.driver_id);
  payload.fuel_limit = Number(payload.fuel_limit);

  setBusy(true);
  setStatus('Mengirim', 'muted');

  try {
    const result = await api('/api/v1/schedules', API_KEYS.schedules, {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    els.resultBox.textContent = JSON.stringify(result, null, 2);
    setStatus('Berhasil', 'ok');
    await loadAll({ silent: true });
  } catch (error) {
    els.resultBox.textContent = JSON.stringify(error.payload || { message: error.message }, null, 2);
    setStatus('Gagal', 'error');
  } finally {
    setBusy(false);
  }
}

async function createVehicle(payload, successMessage = 'Kendaraan ditambahkan') {
  setBusy(true);
  setStatus('Menyimpan kendaraan', 'muted');

  try {
    const result = await api('/api/v1/vehicles', API_KEYS.vehicles, {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    els.resultBox.textContent = JSON.stringify(result, null, 2);
    await loadAll({ silent: true });
    setStatus(successMessage, 'ok');
    return result;
  } catch (error) {
    els.resultBox.textContent = JSON.stringify(error.payload || { message: error.message }, null, 2);
    setStatus('Gagal tambah', 'error');
    throw error;
  } finally {
    setBusy(false);
  }
}

async function handleLogin(event) {
  event.preventDefault();

  const email = els.ssoForm.elements.email.value;
  const password = els.ssoForm.elements.password.value;

  if (!email || !password) {
    setStatus('Email dan password harus diisi', 'error');
    return;
  }

  setBusy(true);
  setStatus('Login...', 'muted');

  try {
    saveSsoSession({
      apiKey: 'dummy-api-key',
      token: 'dummy-token',
      tokenType: 'Bearer',
      source: 'local',
      installedAt: new Date().toISOString(),
    });

    els.ssoForm.reset();
    els.resultBox.textContent = JSON.stringify({
      status: 'success',
      message: 'Login berhasil',
      email: email
    }, null, 2);
    setStatus('Login berhasil', 'ok');
    await loadAll({ silent: true });
  } catch (error) {
    els.resultBox.textContent = JSON.stringify(error.payload || { message: error.message }, null, 2);
    setStatus('Login gagal', 'error');
  } finally {
    setBusy(false);
  }
}

function handleLogout() {
  saveSsoSession(null);
  els.resultBox.textContent = JSON.stringify({
    status: 'success',
    message: 'Logout berhasil',
  }, null, 2);
  setStatus('Logout berhasil', 'ok');
}

function findVehicle(id) {
  return state.vehicles.find(vehicle => String(vehicle.id) === String(id));
}

async function updateVehicle(id, payload, successMessage = 'Kendaraan diperbarui') {
  setBusy(true);
  setStatus('Menyimpan kendaraan', 'muted');

  try {
    const result = await api(`/api/v1/vehicles/${id}`, API_KEYS.vehicles, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    });

    els.resultBox.textContent = JSON.stringify(result, null, 2);
    await loadAll({ silent: true });
    setStatus(successMessage, 'ok');
    return result;
  } catch (error) {
    els.resultBox.textContent = JSON.stringify(error.payload || { message: error.message }, null, 2);
    setStatus('Gagal update', 'error');
    throw error;
  } finally {
    setBusy(false);
  }
}

async function saveVehicleStatus(id) {
  const vehicle = findVehicle(id);
  const select = document.querySelector(`#vehicle-status-${id}`);

  if (!vehicle || !select) {
    return;
  }

  const nextStatus = select.value;

  await updateVehicle(id, {
    status: nextStatus,
    notes: `Status changed from ${vehicle.status} to ${nextStatus} from dispatching web.`,
  }, `Status ${nextStatus}`);
}

function openVehicleDialog(id) {
  const vehicle = findVehicle(id);

  if (!vehicle) {
    setStatus('Kendaraan tidak ditemukan', 'error');
    return;
  }

  els.vehicleDialogTitle.textContent = `${vehicle.plate_number} - ${vehicle.model}`;
  els.vehicleEditForm.elements.id.value = vehicle.id;
  els.vehicleEditForm.elements.vehicle_code.value = vehicle.vehicle_code || '';
  els.vehicleEditForm.elements.plate_number.value = vehicle.plate_number || '';
  els.vehicleEditForm.elements.brand.value = vehicle.brand || '';
  els.vehicleEditForm.elements.model.value = vehicle.model || '';
  els.vehicleEditForm.elements.vehicle_type.value = vehicle.vehicle_type || '';
  els.vehicleEditForm.elements.capacity.value = vehicle.capacity || '';
  els.vehicleEditForm.elements.fuel_type.value = vehicle.fuel_type || '';
  els.vehicleEditForm.elements.status.value = vehicle.status || 'Available';
  els.vehicleEditForm.elements.last_service_date.value = vehicle.last_service_date || '';
  els.vehicleEditForm.elements.notes.value = vehicle.notes || '';
  syncControlAvailability();
  els.vehicleDialog.showModal();
}

async function submitVehicleEdit(event) {
  event.preventDefault();

  const formData = new FormData(els.vehicleEditForm);
  const payload = Object.fromEntries(formData.entries());
  const id = payload.id;
  delete payload.id;

  payload.capacity = Number(payload.capacity);
  payload.last_service_date = payload.last_service_date || null;

  try {
    if (id) {
      await updateVehicle(id, payload, 'Kendaraan tersimpan');
    } else {
      await createVehicle(payload, 'Kendaraan ditambahkan');
    }
    els.vehicleDialog.close();
  } catch (error) {
    // Error detail is already shown in the result panel.
  }
}

els.addVehicleBtn.addEventListener('click', () => {
  els.vehicleEditForm.reset();
  els.vehicleEditForm.elements.id.value = '';
  els.vehicleDialogTitle.textContent = 'Tambah Kendaraan';
  els.vehicleDialog.showModal();
});

els.ssoForm.addEventListener('submit', handleLogin);

els.headerLogoutBtn.addEventListener('click', handleLogout);

els.vehicleRows.addEventListener('click', event => {
  const button = event.target.closest('button[data-action]');

  if (!button) {
    return;
  }

  const id = button.dataset.vehicleId;

  if (button.dataset.action === 'edit-vehicle') {
    openVehicleDialog(id);
  }
});

els.vehicleRows.addEventListener('change', event => {
  const select = event.target.closest('select[data-action="save-status"]');
  if (select) {
    const id = select.dataset.vehicleId;
    saveVehicleStatus(id).catch(() => {});
  }
});

els.vehicleEditForm.addEventListener('submit', submitVehicleEdit);

els.cancelVehicleEditBtn.addEventListener('click', () => {
  els.vehicleDialog.close();
});

els.vehicleEditForm.querySelector('[data-action="close-vehicle-dialog"]').addEventListener('click', () => {
  els.vehicleDialog.close();
});

els.refreshBtn.addEventListener('click', async () => {
  setBusy(true);
  try {
    await loadAll();
  } catch (error) {
    els.resultBox.textContent = JSON.stringify(error.payload || { message: error.message }, null, 2);
    setStatus('Gagal load', 'error');
  } finally {
    setBusy(false);
  }
});

els.resetBtn.addEventListener('click', () => {
  els.form.reset();
  setDefaultDates();
  renderVehicleSelect();
  setStatus('Siap', 'ok');
});

els.form.addEventListener('submit', submitDispatch);

document.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    const targetId = link.getAttribute('href').substring(1);
    
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    link.classList.add('active');

    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(targetId);
    if (targetPage) targetPage.classList.add('active');
  });
});

// Profile Edit Logic
const profileEditBtn = document.querySelector('.profile-edit-btn');
const profileDialog = document.getElementById('profileDialog');
const profileForm = document.getElementById('profileForm');
const profileNameDisplay = document.querySelector('.profile-text strong');
const profileAvatarDisplay = document.querySelector('.profile-avatar');
const profileNameInput = document.getElementById('profileNameInput');

if (profileEditBtn && profileDialog) {
  profileEditBtn.addEventListener('click', () => {
    profileNameInput.value = profileNameDisplay.textContent;
    profileDialog.showModal();
  });
}

if (profileForm) {
  profileForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const newName = profileNameInput.value.trim() || 'Admin';
    profileNameDisplay.textContent = newName;
    
    // Generate initials
    const words = newName.split(' ').filter(w => w.length > 0);
    let initials = '';
    if (words.length >= 2) {
      initials = words[0][0] + words[1][0];
    } else if (words.length === 1) {
      initials = words[0].substring(0, 2);
    }
    profileAvatarDisplay.textContent = initials.toUpperCase();
    
    profileDialog.close();
  });
}

setDefaultDates();
renderSsoState();
syncControlAvailability();

if (state.sso?.token) {
  loadAll().catch(error => {
    els.resultBox.textContent = JSON.stringify(error.payload || { message: error.message }, null, 2);
    setStatus('Gagal load', 'error');
    syncControlAvailability();
  });
}
