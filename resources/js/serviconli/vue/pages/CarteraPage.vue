<template>
  <v-app>
    <v-main class="bg-grey-lighten-4">
      <v-container fluid>
        <v-row>
          <v-col cols="12">
            <h1 class="text-h4 mb-4">Cartera y Facturación</h1>
          </v-col>
        </v-row>

        <!-- Tabs de navegación -->
        <v-tabs v-model="activeTab" color="primary">
          <v-tab value="cuentas">Cuentas de Cobro</v-tab>
          <v-tab value="recibos">Recibos de Caja</v-tab>
        </v-tabs>

        <v-window v-model="activeTab">
          <!-- TAB: Cuentas de Cobro -->
          <v-window-item value="cuentas">
            <v-card class="mt-4">
              <v-card-title class="d-flex align-center">
                <span>Cuentas de Cobro</span>
                <v-spacer />
                <v-btn color="primary" @click="showNewCuenta = true">Nueva Cuenta</v-btn>
              </v-card-title>

              <v-card-text>
                <v-data-table
                  :headers="cuentaHeaders"
                  :items="cuentas"
                  :loading="loadingCuentas"
                  density="compact"
                  hover
                  @click:row="(_, { item }) => selectCuenta(item)"
                >
                  <template #[`item.total_1`]="{ item }">
                    {{ formatCurrency(item.total_1) }}
                  </template>
                  <template #[`item.total_2`]="{ item }">
                    {{ formatCurrency(item.total_2) }}
                  </template>
                  <template #[`item.status`]="{ item }">
                    <v-chip :color="statusColor(item.status)" size="small">{{ item.status }}</v-chip>
                  </template>
                  <template #[`item.actions`]="{ item }">
                    <v-btn v-if="item.status === 'PRE_CUENTA'" size="small" color="success" variant="text" @click.stop="openDefinitiva(item)">
                      Definitiva
                    </v-btn>
                    <v-btn v-if="item.status === 'DEFINITIVA'" size="small" color="primary" variant="text" @click.stop="openPay(item)">
                      Pagar
                    </v-btn>
                    <v-btn v-if="item.status !== 'PAGADA' && item.status !== 'ANULADA'" size="small" color="error" variant="text" @click.stop="openCancelCuenta(item)">
                      Anular
                    </v-btn>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-window-item>

          <!-- TAB: Recibos -->
          <v-window-item value="recibos">
            <v-card class="mt-4">
              <v-card-title class="d-flex align-center">
                <span>Recibos de Caja</span>
                <v-spacer />
                <v-btn color="primary" @click="showNewRecibo = true">Nuevo Recibo</v-btn>
              </v-card-title>

              <v-card-text>
                <v-row class="mb-4">
                  <v-col cols="3">
                    <v-select v-model="invoiceFilter.tipo" :items="tiposRecibo" label="Tipo" clearable density="compact" @update:model-value="loadInvoices" />
                  </v-col>
                  <v-col cols="3">
                    <v-select v-model="invoiceFilter.estado" :items="['ACTIVO','ANULADO']" label="Estado" clearable density="compact" @update:model-value="loadInvoices" />
                  </v-col>
                </v-row>

                <v-data-table
                  :headers="invoiceHeaders"
                  :items="invoices"
                  :loading="loadingInvoices"
                  density="compact"
                  hover
                  @click:row="(_, { item }) => selectInvoice(item)"
                >
                  <template #[`item.total_pesos`]="{ item }">
                    {{ formatCurrency(item.total_pesos) }}
                  </template>
                  <template #[`item.estado`]="{ item }">
                    <v-chip :color="item.estado === 'ACTIVO' ? 'success' : 'error'" size="small">{{ item.estado }}</v-chip>
                  </template>
                  <template #[`item.actions`]="{ item }">
                    <v-btn v-if="item.estado === 'ACTIVO'" size="small" color="error" variant="text" @click.stop="openCancelInvoice(item)">
                      Anular
                    </v-btn>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-window-item>
        </v-window>

        <!-- Dialog: Detalle Cuenta de Cobro -->
        <v-dialog v-model="showCuentaDetail" max-width="800">
          <v-card v-if="selectedCuenta">
            <v-card-title>Cuenta {{ selectedCuenta.cuenta_number }}</v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="4"><strong>Pagador:</strong> {{ selectedCuenta.payer?.razon_social }}</v-col>
                <v-col cols="4"><strong>Período:</strong> {{ selectedCuenta.period_year }}-{{ String(selectedCuenta.period_month).padStart(2,'0') }}</v-col>
                <v-col cols="4"><strong>Modo:</strong> {{ selectedCuenta.generation_mode }}</v-col>
              </v-row>
              <v-divider class="my-3" />
              <v-row>
                <v-col cols="2"><strong>EPS:</strong> {{ formatCurrency(selectedCuenta.total_eps) }}</v-col>
                <v-col cols="2"><strong>AFP:</strong> {{ formatCurrency(selectedCuenta.total_afp) }}</v-col>
                <v-col cols="2"><strong>ARL:</strong> {{ formatCurrency(selectedCuenta.total_arl) }}</v-col>
                <v-col cols="2"><strong>CCF:</strong> {{ formatCurrency(selectedCuenta.total_ccf) }}</v-col>
                <v-col cols="2"><strong>Admin:</strong> {{ formatCurrency(selectedCuenta.total_admin) }}</v-col>
                <v-col cols="2"><strong>Afiliación:</strong> {{ formatCurrency(selectedCuenta.total_affiliation) }}</v-col>
              </v-row>
              <v-row class="mt-2">
                <v-col cols="4"><strong>Total Oportuno:</strong> {{ formatCurrency(selectedCuenta.total_1) }}</v-col>
                <v-col cols="4"><strong>Intereses Mora:</strong> {{ formatCurrency(selectedCuenta.interest_mora) }}</v-col>
                <v-col cols="4"><strong>Total con Mora:</strong> {{ formatCurrency(selectedCuenta.total_2) }}</v-col>
              </v-row>
              <v-divider class="my-3" />
              <h4>Detalle por Afiliado</h4>
              <v-data-table
                v-if="selectedCuenta.details"
                :headers="detailHeaders"
                :items="selectedCuenta.details"
                density="compact"
              >
                <template #[`item.total_pesos`]="{ item }">
                  {{ formatCurrency(item.total_pesos) }}
                </template>
              </v-data-table>
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showCuentaDetail = false">Cerrar</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Nueva Cuenta -->
        <v-dialog v-model="showNewCuenta" max-width="500">
          <v-card>
            <v-card-title>Generar Cuenta de Cobro</v-card-title>
            <v-card-text>
              <v-select v-model="newCuenta.payer_id" :items="payers" item-title="razon_social" item-value="id" label="Pagador" />
              <v-row>
                <v-col cols="6"><v-text-field v-model.number="newCuenta.period_year" label="Año" type="number" /></v-col>
                <v-col cols="6"><v-text-field v-model.number="newCuenta.period_month" label="Mes" type="number" min="1" max="12" /></v-col>
              </v-row>
              <v-select v-model="newCuenta.mode" :items="['PLENO','SOLO_APORTES','SOLO_AFILIACIONES']" label="Modo" />
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showNewCuenta = false">Cancelar</v-btn>
              <v-btn color="primary" :loading="savingCuenta" @click="createCuenta">Generar Pre-Cuenta</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Definitiva -->
        <v-dialog v-model="showDefinitiva" max-width="500">
          <v-card>
            <v-card-title>Convertir a Definitiva</v-card-title>
            <v-card-text>
              <v-text-field v-model="definitivaForm.payment_date_1" label="Fecha pago oportuno" type="date" />
              <v-text-field v-model="definitivaForm.payment_date_2" label="Fecha pago con mora" type="date" />
              <v-text-field v-model.number="definitivaForm.mora_days" label="Días de mora" type="number" min="0" />
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showDefinitiva = false">Cancelar</v-btn>
              <v-btn color="success" :loading="savingDefinitiva" @click="submitDefinitiva">Confirmar</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Pagar Cuenta -->
        <v-dialog v-model="showPay" max-width="500">
          <v-card>
            <v-card-title>Pagar Cuenta de Cobro</v-card-title>
            <v-card-text v-if="payingCuenta">
              <v-alert type="info" class="mb-4">
                <strong>{{ payingCuenta.isOportuno ? 'Pago Oportuno' : 'Pago con Mora' }}:</strong>
                {{ formatCurrency(payingCuenta.isOportuno ? payingCuenta.total_1 : payingCuenta.total_2) }}
              </v-alert>
              <v-select v-model="payForm.payment_method" :items="['EFECTIVO','CONSIGNACION']" label="Medio de pago" />
              <v-text-field v-model.number="payForm.amount_pesos" label="Monto" type="number" prefix="$" />
              <v-text-field v-if="payForm.payment_method === 'CONSIGNACION'" v-model="payForm.bank_name" label="Banco" />
              <v-text-field v-if="payForm.payment_method === 'CONSIGNACION'" v-model="payForm.bank_reference" label="Referencia" />
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showPay = false">Cancelar</v-btn>
              <v-btn color="primary" :loading="paying" @click="submitPay">Registrar Pago</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Anular Cuenta -->
        <v-dialog v-model="showCancelCuenta" max-width="500">
          <v-card>
            <v-card-title>Anular Cuenta de Cobro</v-card-title>
            <v-card-text>
              <v-text-field v-model="cancelForm.cancellation_reason" label="Causal" />
              <v-textarea v-model="cancelForm.cancellation_motive" label="Motivo" rows="3" />
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showCancelCuenta = false">Cancelar</v-btn>
              <v-btn color="error" :loading="cancelling" @click="submitCancelCuenta">Anular</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Nuevo Recibo -->
        <v-dialog v-model="showNewRecibo" max-width="600">
          <v-card>
            <v-card-title>Nuevo Recibo de Caja</v-card-title>
            <v-card-text>
              <v-select v-model="newRecibo.tipo" :items="tiposRecibo" label="Tipo" />
              <v-select v-model="newRecibo.payment_method" :items="['EFECTIVO','CONSIGNACION','CREDITO']" label="Medio de pago" />
              <v-text-field v-if="newRecibo.payment_method === 'CONSIGNACION'" v-model="newRecibo.bank_name" label="Banco" />
              <v-text-field v-if="newRecibo.payment_method === 'CONSIGNACION'" v-model="newRecibo.bank_reference" label="Referencia" />

              <h4 class="mt-3 mb-2">Conceptos</h4>
              <v-row v-for="(item, idx) in newRecibo.items" :key="idx" dense>
                <v-col cols="7"><v-text-field v-model="item.concept" label="Concepto" density="compact" /></v-col>
                <v-col cols="4"><v-text-field v-model.number="item.amount_pesos" label="Monto" type="number" prefix="$" density="compact" /></v-col>
                <v-col cols="1" class="d-flex align-center">
                  <v-btn icon size="small" color="error" @click="newRecibo.items.splice(idx, 1)"><v-icon>mdi-close</v-icon></v-btn>
                </v-col>
              </v-row>
              <v-btn size="small" variant="text" @click="newRecibo.items.push({ concept: '', amount_pesos: 0 })">+ Agregar concepto</v-btn>
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showNewRecibo = false">Cancelar</v-btn>
              <v-btn color="primary" :loading="savingRecibo" @click="createRecibo">Generar Recibo</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Detalle Recibo -->
        <v-dialog v-model="showInvoiceDetail" max-width="700">
          <v-card v-if="selectedInvoice">
            <v-card-title>Recibo {{ selectedInvoice.invoice?.public_number }}</v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="4"><strong>Tipo:</strong> {{ selectedInvoice.invoice?.tipo }}</v-col>
                <v-col cols="4"><strong>Medio:</strong> {{ selectedInvoice.invoice?.payment_method }}</v-col>
                <v-col cols="4"><strong>Fecha:</strong> {{ selectedInvoice.invoice?.fecha }}</v-col>
              </v-row>
              <v-row class="mt-2">
                <v-col cols="6"><strong>Total:</strong> {{ formatCurrency(selectedInvoice.invoice?.total_pesos) }}</v-col>
                <v-col cols="6"><strong>En letras:</strong> {{ selectedInvoice.total_in_words }}</v-col>
              </v-row>
              <v-divider class="my-3" />
              <v-data-table
                v-if="selectedInvoice.invoice?.items"
                :headers="itemHeaders"
                :items="selectedInvoice.invoice.items"
                density="compact"
              >
                <template #[`item.amount_pesos`]="{ item }">
                  {{ formatCurrency(item.amount_pesos) }}
                </template>
              </v-data-table>
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showInvoiceDetail = false">Cerrar</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Dialog: Anular Recibo -->
        <v-dialog v-model="showCancelInvoice" max-width="500">
          <v-card>
            <v-card-title>Anular Recibo</v-card-title>
            <v-card-text>
              <v-text-field v-model="cancelInvoiceForm.cancellation_reason" label="Causal" />
              <v-textarea v-model="cancelInvoiceForm.cancellation_motive" label="Motivo" rows="3" />
            </v-card-text>
            <v-card-actions>
              <v-spacer />
              <v-btn @click="showCancelInvoice = false">Cancelar</v-btn>
              <v-btn color="error" :loading="cancellingInvoice" @click="submitCancelInvoice">Anular</v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000">{{ snackbar.text }}</v-snackbar>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';

const API = '/api';
const token = sessionStorage.getItem('api_token') || '';
const headers = () => ({
  Authorization: `Bearer ${token}`,
  Accept: 'application/json',
  'Content-Type': 'application/json',
});

const activeTab = ref('cuentas');
const snackbar = reactive({ show: false, text: '', color: 'success' });
const notify = (text, color = 'success') => { snackbar.text = text; snackbar.color = color; snackbar.show = true; };

// --- Cuentas de Cobro ---
const cuentas = ref([]);
const loadingCuentas = ref(false);
const cuentaHeaders = [
  { title: 'Número', key: 'cuenta_number' },
  { title: 'Pagador', key: 'payer.razon_social' },
  { title: 'Período', key: 'period_cobro' },
  { title: 'Modo', key: 'generation_mode' },
  { title: 'Total Oportuno', key: 'total_1' },
  { title: 'Total Mora', key: 'total_2' },
  { title: 'Estado', key: 'status' },
  { title: 'Acciones', key: 'actions', sortable: false },
];

const detailHeaders = [
  { title: 'Afiliado', key: 'affiliate_id' },
  { title: 'Salud', key: 'health_pesos' },
  { title: 'Pensión', key: 'pension_pesos' },
  { title: 'ARL', key: 'arl_pesos' },
  { title: 'CCF', key: 'ccf_pesos' },
  { title: 'Admin', key: 'admin_pesos' },
  { title: 'Total', key: 'total_pesos' },
];

const selectedCuenta = ref(null);
const showCuentaDetail = ref(false);

async function loadCuentas() {
  loadingCuentas.value = true;
  try {
    const res = await fetch(`${API}/cuentas-cobro`, { headers: headers() });
    const data = await res.json();
    cuentas.value = data.data || [];
  } catch (e) { notify('Error cargando cuentas', 'error'); }
  loadingCuentas.value = false;
}

async function selectCuenta(item) {
  try {
    const res = await fetch(`${API}/cuentas-cobro/${item.id}`, { headers: headers() });
    selectedCuenta.value = await res.json();
    showCuentaDetail.value = true;
  } catch (e) { notify('Error cargando detalle', 'error'); }
}

// Nueva Cuenta
const showNewCuenta = ref(false);
const savingCuenta = ref(false);
const payers = ref([]);
const newCuenta = reactive({ payer_id: null, period_year: new Date().getFullYear(), period_month: new Date().getMonth() + 1, mode: 'PLENO' });

async function loadPayers() {
  try {
    const res = await fetch(`${API}/batches/payers`, { headers: headers() });
    payers.value = await res.json();
  } catch (e) { /* ignore */ }
}

async function createCuenta() {
  savingCuenta.value = true;
  try {
    const res = await fetch(`${API}/cuentas-cobro`, { method: 'POST', headers: headers(), body: JSON.stringify(newCuenta) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.message || 'Error'); }
    notify('Pre-cuenta generada');
    showNewCuenta.value = false;
    await loadCuentas();
  } catch (e) { notify(e.message, 'error'); }
  savingCuenta.value = false;
}

// Definitiva
const showDefinitiva = ref(false);
const savingDefinitiva = ref(false);
const definitivaCuentaId = ref(null);
const definitivaForm = reactive({ payment_date_1: '', payment_date_2: '', mora_days: 0 });

function openDefinitiva(item) {
  definitivaCuentaId.value = item.id;
  showDefinitiva.value = true;
}

async function submitDefinitiva() {
  savingDefinitiva.value = true;
  try {
    const res = await fetch(`${API}/cuentas-cobro/${definitivaCuentaId.value}/definitiva`, { method: 'POST', headers: headers(), body: JSON.stringify(definitivaForm) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.message || 'Error'); }
    notify('Cuenta convertida a definitiva');
    showDefinitiva.value = false;
    await loadCuentas();
  } catch (e) { notify(e.message, 'error'); }
  savingDefinitiva.value = false;
}

// Pagar
const showPay = ref(false);
const paying = ref(false);
const payingCuenta = ref(null);
const payForm = reactive({ payment_method: 'EFECTIVO', amount_pesos: 0, bank_name: '', bank_reference: '' });

function openPay(item) {
  payingCuenta.value = { ...item, isOportuno: !item.payment_date_1 || new Date() <= new Date(item.payment_date_1) };
  payForm.amount_pesos = payingCuenta.value.isOportuno ? item.total_1 : item.total_2;
  showPay.value = true;
}

async function submitPay() {
  paying.value = true;
  try {
    const res = await fetch(`${API}/cuentas-cobro/${payingCuenta.value.id}/pay`, { method: 'POST', headers: headers(), body: JSON.stringify(payForm) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.message || 'Error'); }
    notify('Pago registrado exitosamente');
    showPay.value = false;
    await loadCuentas();
  } catch (e) { notify(e.message, 'error'); }
  paying.value = false;
}

// Anular Cuenta
const showCancelCuenta = ref(false);
const cancelling = ref(false);
const cancelCuentaId = ref(null);
const cancelForm = reactive({ cancellation_reason: '', cancellation_motive: '' });

function openCancelCuenta(item) {
  cancelCuentaId.value = item.id;
  showCancelCuenta.value = true;
}

async function submitCancelCuenta() {
  cancelling.value = true;
  try {
    const res = await fetch(`${API}/cuentas-cobro/${cancelCuentaId.value}/cancel`, { method: 'POST', headers: headers(), body: JSON.stringify(cancelForm) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.message || 'Error'); }
    notify('Cuenta anulada');
    showCancelCuenta.value = false;
    await loadCuentas();
  } catch (e) { notify(e.message, 'error'); }
  cancelling.value = false;
}

// --- Recibos ---
const invoices = ref([]);
const loadingInvoices = ref(false);
const tiposRecibo = ['AFILIACION', 'APORTE', 'REINGRESO', 'CUENTA', 'CAJA_GENERAL'];
const invoiceFilter = reactive({ tipo: null, estado: null });
const invoiceHeaders = [
  { title: 'Número', key: 'public_number' },
  { title: 'Fecha', key: 'fecha' },
  { title: 'Tipo', key: 'tipo' },
  { title: 'Medio', key: 'payment_method' },
  { title: 'Total', key: 'total_pesos' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
];

const itemHeaders = [
  { title: '#', key: 'line_number' },
  { title: 'Concepto', key: 'concept' },
  { title: 'Monto', key: 'amount_pesos' },
];

async function loadInvoices() {
  loadingInvoices.value = true;
  try {
    const params = new URLSearchParams();
    if (invoiceFilter.tipo) params.set('tipo', invoiceFilter.tipo);
    if (invoiceFilter.estado) params.set('estado', invoiceFilter.estado);
    const res = await fetch(`${API}/invoices?${params}`, { headers: headers() });
    const data = await res.json();
    invoices.value = data.data || [];
  } catch (e) { notify('Error cargando recibos', 'error'); }
  loadingInvoices.value = false;
}

// Detalle recibo
const selectedInvoice = ref(null);
const showInvoiceDetail = ref(false);

async function selectInvoice(item) {
  try {
    const res = await fetch(`${API}/invoices/${item.id}`, { headers: headers() });
    selectedInvoice.value = await res.json();
    showInvoiceDetail.value = true;
  } catch (e) { notify('Error cargando recibo', 'error'); }
}

// Nuevo Recibo
const showNewRecibo = ref(false);
const savingRecibo = ref(false);
const newRecibo = reactive({
  tipo: 'CAJA_GENERAL',
  payment_method: 'EFECTIVO',
  bank_name: '',
  bank_reference: '',
  items: [{ concept: '', amount_pesos: 0 }],
});

async function createRecibo() {
  savingRecibo.value = true;
  try {
    const res = await fetch(`${API}/invoices`, { method: 'POST', headers: headers(), body: JSON.stringify(newRecibo) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.message || 'Error'); }
    notify('Recibo generado exitosamente');
    showNewRecibo.value = false;
    await loadInvoices();
  } catch (e) { notify(e.message, 'error'); }
  savingRecibo.value = false;
}

// Anular recibo
const showCancelInvoice = ref(false);
const cancellingInvoice = ref(false);
const cancelInvoiceId = ref(null);
const cancelInvoiceForm = reactive({ cancellation_reason: '', cancellation_motive: '' });

function openCancelInvoice(item) {
  cancelInvoiceId.value = item.id;
  showCancelInvoice.value = true;
}

async function submitCancelInvoice() {
  cancellingInvoice.value = true;
  try {
    const res = await fetch(`${API}/invoices/${cancelInvoiceId.value}/cancel`, { method: 'POST', headers: headers(), body: JSON.stringify(cancelInvoiceForm) });
    if (!res.ok) { const e = await res.json(); throw new Error(e.message || 'Error'); }
    notify('Recibo anulado');
    showCancelInvoice.value = false;
    await loadInvoices();
  } catch (e) { notify(e.message, 'error'); }
  cancellingInvoice.value = false;
}

function formatCurrency(v) {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v || 0);
}

function statusColor(s) {
  return { PRE_CUENTA: 'orange', DEFINITIVA: 'blue', PAGADA: 'success', ANULADA: 'error' }[s] || 'grey';
}

onMounted(() => {
  loadCuentas();
  loadInvoices();
  loadPayers();
});
</script>
