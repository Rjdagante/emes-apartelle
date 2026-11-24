<template>
  <div class="payments-view">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden animate-fade-up">
      <div class="card-header bg-white py-4 px-4 border-0 d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <h5 class="serif-font fw-bold mb-0 text-secondary-dark">Payment Transactions</h5>
          <button @click="openPrintModal" class="btn btn-sm btn-dark rounded-3 d-flex align-items-center gap-2 px-3 py-1.5 shadow-sm fw-bold transition-all hover-scale">
            <i class="bi bi-printer text-gold"></i>
            <span>Print Daily Report</span>
          </button>
        </div>
        
        <!-- Date Filters -->
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">From:</span>
            <input type="date" class="form-control form-control-sm border bg-light px-2.5 rounded-3" v-model="startDate" style="width: 140px; font-size: 0.85rem; font-weight: 500;">
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">To:</span>
            <input type="date" class="form-control form-control-sm border bg-light px-2.5 rounded-3" v-model="endDate" style="width: 140px; font-size: 0.85rem; font-weight: 500;">
          </div>
          <button v-if="startDate || endDate" @click="clearDateFilters" class="btn btn-sm btn-outline-danger rounded-3 d-flex align-items-center gap-1 px-3" style="font-size: 0.8rem; font-weight: 600;">
            <i class="bi bi-x-lg"></i>
            <span>Clear</span>
          </button>
        </div>
      </div>
      <div class="card-body p-0">
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-gold" role="status"></div>
          <p class="text-muted mt-2 small">Loading transactions...</p>
        </div>
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th class="ps-4 border-0 text-muted small fw-bold text-uppercase">Transaction ID</th>
                <th class="border-0 text-muted small fw-bold text-uppercase">Guest</th>
                <th class="border-0 text-muted small fw-bold text-uppercase">Method</th>
                <th class="border-0 text-muted small fw-bold text-uppercase">Amount</th>
                <th class="border-0 text-muted small fw-bold text-uppercase">Date</th>
                <th class="pe-4 border-0 text-muted small fw-bold text-uppercase text-end">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pay in paginatedPayments" :key="pay.id">
                <td class="ps-4 py-3"><span class="small fw-bold text-secondary-dark">#TXN-{{ pay.id }}</span></td>
                <td>
                  <div class="d-flex flex-column" v-if="pay.reservation?.user">
                    <span class="small fw-bold text-secondary-dark">{{ pay.reservation.user.name }}</span>
                    <small class="text-muted" v-if="pay.reservation.user.phone">
                      <i class="bi bi-telephone small me-1"></i>{{ pay.reservation.user.phone }}
                    </small>
                  </div>
                  <span v-else class="small text-muted italic">No Guest Info</span>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi text-muted opacity-50" :class="isQrPayment(pay) ? 'bi-qr-code-scan' : 'bi-credit-card-2-back'"></i>
                    <span class="small fw-medium">{{ mapPaymentMethod(pay.method, pay.reservation) }}</span>
                  </div>
                </td>
                <td><span class="fw-bold text-secondary-dark">₱{{ formatAmount(pay.amount) }}</span></td>
                <td><span class="small text-muted">{{ formatDate(pay.created_at) }}</span></td>
                <td class="pe-4 text-end">
                   <span class="badge rounded-pill fw-bold text-uppercase" :class="getStatusClass(pay.status)" style="font-size: 0.65rem;">
                     {{ pay.status }}
                   </span>
                </td>
              </tr>
              <tr v-if="filteredPayments.length === 0">
                <td colspan="6" class="text-center py-5 text-muted">No transactions found for this filter.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <AdminPagination 
          :current-page="currentPage" 
          :total-items="filteredPayments.length" 
          :page-size="pageSize"
          @change="page => currentPage = page"
          @page-size-change="size => { pageSize = size; currentPage = 1; }"
        />
      </div>
    </div>

    <!-- Daily Payment Print Preview Modal -->
    <teleport to="body">
      <div v-if="showPrintModal" class="print-modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in" style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 9999;">
        <div class="print-modal-dialog bg-white rounded-4 shadow-2xl d-flex flex-column overflow-hidden animate-scale-up" style="width: 100%; max-width: 860px; max-height: 92vh;">
          
          <!-- Modal Top Bar -->
          <div class="modal-header bg-light-subtle px-4 py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
              <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 34px; height: 34px;">
                <i class="bi bi-printer text-gold"></i>
              </div>
              <div>
                <h6 class="mb-0 fw-bold text-secondary-dark">Daily Transactions Report</h6>
                <small class="text-muted">Preview summary and print daily report</small>
              </div>
            </div>
            
            <div class="d-flex align-items-center gap-2">
              <!-- Date Selector -->
              <div class="d-flex align-items-center gap-2 bg-white px-2.5 py-1.5 rounded-3 border shadow-sm">
                <span class="small fw-bold text-muted text-uppercase" style="font-size: 0.7rem;">Date:</span>
                <input type="date" class="form-control form-control-sm border-0 p-0 shadow-none fw-bold text-dark" v-model="selectedReportDate" style="width: 130px; font-size: 0.85rem;">
              </div>
              <button class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1 small fw-semibold" @click="setReportToday" title="Set to Today">Today</button>
              <button class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1 small fw-semibold" @click="setReportYesterday" title="Set to Yesterday">Yesterday</button>
              <button class="btn btn-sm btn-outline-secondary rounded-circle p-1 ms-2" @click="closePrintModal" style="width: 32px; height: 32px;">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </div>

          <!-- Printable Report Container (Scrollable Preview) -->
          <div class="modal-body p-4 overflow-y-auto custom-scrollbar flex-grow-1 bg-light-soft">
            <div id="daily-report-printable" class="bg-white p-4 p-md-5 rounded-3 border shadow-sm mx-auto" style="max-width: 760px; color: #0f172a;">
              
              <!-- Report Header -->
              <div class="header d-flex justify-content-between align-items-start pb-3 mb-4 border-bottom border-2 border-dark">
                <div>
                  <h3 class="serif-font fw-bold mb-0 text-dark" style="letter-spacing: 0.5px;">EME's Apartelle</h3>
                  <p class="mb-0 text-muted small text-uppercase fw-semibold" style="letter-spacing: 1.5px; font-size: 0.75rem;">Hospitality & Accommodation</p>
                </div>
                <div class="text-end">
                  <h5 class="fw-bold text-dark mb-0 text-uppercase" style="letter-spacing: 0.5px;">Daily Payment Report</h5>
                  <p class="mb-0 text-secondary fw-semibold small">{{ formatReportDateHeader(selectedReportDate) }}</p>
                </div>
              </div>

              <!-- Summary Cards -->
              <div class="summary-cards row g-3 mb-4">
                <div class="col-6 col-md-4">
                  <div class="card p-3 bg-light rounded-3 border text-start">
                    <small class="card-title text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Transactions</small>
                    <span class="card-value fs-4 fw-bold text-dark">{{ dailyPayments.length }}</span>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="card p-3 bg-light rounded-3 border text-start">
                    <small class="card-title text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Total Revenue</small>
                    <span class="card-value success fs-4 fw-bold text-success">₱{{ formatAmount(dailyTotalSuccess) }}</span>
                  </div>
                </div>
                <div class="col-12 col-md-4">
                  <div class="card p-3 bg-light rounded-3 border text-start">
                    <small class="card-title text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Payment Methods</small>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                      <span v-for="(amount, method) in dailyMethodBreakdown" :key="method" class="badge bg-white text-dark border" style="font-size: 0.75rem; font-weight: 600;">
                        {{ method }}: ₱{{ formatAmount(amount) }}
                      </span>
                      <span v-if="Object.keys(dailyMethodBreakdown).length === 0" class="text-muted small italic">None</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Transactions Table -->
              <div class="table-container mb-4">
                <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                  <thead class="table-dark">
                    <tr>
                      <th class="py-2 px-3 text-uppercase" style="font-size: 0.72rem;">TXN ID</th>
                      <th class="py-2 px-3 text-uppercase" style="font-size: 0.72rem;">Time</th>
                      <th class="py-2 px-3 text-uppercase" style="font-size: 0.72rem;">Guest</th>
                      <th class="py-2 px-3 text-uppercase" style="font-size: 0.72rem;">Method</th>
                      <th class="py-2 px-3 text-uppercase text-end" style="font-size: 0.72rem;">Amount</th>
                      <th class="py-2 px-3 text-uppercase text-center" style="font-size: 0.72rem;">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="pay in dailyPayments" :key="pay.id">
                      <td class="py-2 px-3 fw-bold">#TXN-{{ pay.id }}</td>
                      <td class="py-2 px-3 text-muted">{{ formatTimeOnly(pay.created_at) }}</td>
                      <td class="py-2 px-3">
                        <div class="fw-semibold text-dark">{{ pay.reservation?.user?.name || 'Walk-in Guest' }}</div>
                        <small v-if="pay.reservation?.user?.phone" class="text-muted d-block" style="font-size: 0.75rem;">
                          {{ pay.reservation.user.phone }}
                        </small>
                      </td>
                      <td class="py-2 px-3">{{ mapPaymentMethod(pay.method, pay.reservation) }}</td>
                      <td class="py-2 px-3 text-end fw-bold text-dark">₱{{ formatAmount(pay.amount) }}</td>
                      <td class="py-2 px-3 text-center">
                        <span class="badge rounded-pill fw-bold text-uppercase" :class="getStatusClass(pay.status)" style="font-size: 0.62rem;">
                          {{ pay.status }}
                        </span>
                      </td>
                    </tr>
                    <tr v-if="dailyPayments.length === 0">
                      <td colspan="6" class="text-center py-5 text-muted fst-italic">
                        No payment transactions recorded for this date.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Sign-off & Verification -->
              <div class="sign-section pt-4 mt-4 border-top d-flex justify-content-between align-items-end" style="font-size: 0.8rem;">
                <div>
                  <p class="mb-1 text-muted small">Report Prepared By:</p>
                  <div class="fw-bold text-dark">{{ state.user?.name || 'Staff' }} ({{ state.user?.role === 'staff' ? 'Staff' : 'Administrator' }})</div>
                  <small class="text-muted">Generated: {{ formatCurrentDateTime() }}</small>
                </div>
                <div class="sign-col text-end" style="width: 220px;">
                  <div class="sign-line border-bottom border-dark mb-1" style="height: 35px;"></div>
                  <span class="sign-caption text-muted small">Authorized Signature / Verified By</span>
                </div>
              </div>

              <!-- Print Document Footer Note -->
              <div class="footer text-center text-muted mt-4 pt-2 border-top border-dashed small" style="font-size: 0.72rem;">
                This is an official transaction summary generated by EME's Apartelle Management System.
              </div>

            </div>
          </div>

          <!-- Modal Footer Actions -->
          <div class="modal-footer bg-white px-4 py-3 border-top d-flex align-items-center justify-content-between">
            <span class="small text-muted">
              Total <strong>{{ dailyPayments.length }}</strong> transaction(s) for <strong>{{ formatReportDateHeader(selectedReportDate) }}</strong>
            </span>
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-light border px-3 rounded-3 fw-semibold" @click="closePrintModal">
                Cancel
              </button>
              <button class="btn btn-sm btn-dark px-4 rounded-3 d-flex align-items-center gap-2 fw-bold shadow-sm" @click="printReport" :disabled="dailyPayments.length === 0">
                <i class="bi bi-printer-fill text-gold"></i>
                <span>Print Document</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import AdminPagination from '../../components/AdminPagination.vue';
import axios from 'axios';
import { useAuth } from '../../store/auth';

const { state } = useAuth();

const filter = ref('all'); 
const searchQuery = ref('');
const startDate = ref('');
const endDate = ref('');
const currentPage = ref(1);
const pageSize = ref(5);
const loading = ref(true);
const payments = ref([]);

// Print Modal State
const showPrintModal = ref(false);
const selectedReportDate = ref('');

const clearDateFilters = () => {
  startDate.value = '';
  endDate.value = '';
};

const getLocalDateString = (dateString) => {
  if (!dateString) return '';
  const d = new Date(dateString);
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const fetchPayments = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/api/admin/payments');
    payments.value = response.data;
  } catch (error) {
    console.error('Failed to fetch payments', error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchPayments);

const formatAmount = (amount) => {
  return parseFloat(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  const datePart = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const timePart = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  return `${datePart} ${timePart}`;
};

const formatTimeOnly = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};

const formatReportDateHeader = (dateStr) => {
  if (!dateStr) return 'N/A';
  const parts = dateStr.split('-');
  if (parts.length === 3) {
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }
  return dateStr;
};

const formatCurrentDateTime = () => {
  const now = new Date();
  return now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
};

const getStatusClass = (status) => {
  const s = String(status).toLowerCase();
  if (s === 'succeeded' || s === 'successful' || s === 'paid') {
    return 'bg-success-subtle text-success';
  }
  if (s === 'failed' || s === 'cancelled') {
    return 'bg-danger-subtle text-danger';
  }
  return 'bg-warning-subtle text-warning';
};

const isQrPayment = (pay) => {
  const m = String(pay?.method || '').toLowerCase();
  return m === 'qr payment' || m === 'qr_payment' || m === 'qr_code' || m === 'qrph' || Boolean(pay?.reservation?.payment_screenshot);
};

const mapPaymentMethod = (method, reservation = null) => {
  if (reservation?.payment_screenshot) return 'QR Payment';
  if (!method) return 'N/A';
  const m = method.toLowerCase();
  const map = {
    'qr payment': 'QR Payment',
    'qr_payment': 'QR Payment',
    'gcash': 'GCash (E-Wallet)',
    'grab_pay': 'GrabPay (E-Wallet)',
    'maya': 'Maya / PayMaya',
    'paymaya': 'Maya / PayMaya',
    'card': 'Credit/Debit Card',
    'credit_card': 'Credit/Debit Card',
    'qrph': 'QR Payment',
    'qr_code': 'QR Payment',
    'ewallet': 'E-Wallet',
    'dob': 'Direct Online Banking',
    'dob_ubp': 'UnionBank (Online)',
    'cash/manual': 'Cash (Walk-in)',
    'checkout_session': 'Online Payment',
    'xendit': 'Online Settlement (Xendit)'
  };
  return map[m] || method.toUpperCase();
};

const filteredPayments = computed(() => {
  let list = payments.value;

  if (startDate.value) {
    list = list.filter(p => {
      const pDateStr = getLocalDateString(p.created_at);
      return pDateStr >= startDate.value;
    });
  }

  if (endDate.value) {
    list = list.filter(p => {
      const pDateStr = getLocalDateString(p.created_at);
      return pDateStr <= endDate.value;
    });
  }

  return list;
});

const paginatedPayments = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return filteredPayments.value.slice(start, end);
});

// Daily Report Computations
const openPrintModal = () => {
  selectedReportDate.value = startDate.value || getLocalDateString(new Date());
  showPrintModal.value = true;
};

const closePrintModal = () => {
  showPrintModal.value = false;
};

const setReportToday = () => {
  selectedReportDate.value = getLocalDateString(new Date());
};

const setReportYesterday = () => {
  const d = new Date();
  d.setDate(d.getDate() - 1);
  selectedReportDate.value = getLocalDateString(d);
};

const dailyPayments = computed(() => {
  if (!selectedReportDate.value) return [];
  return payments.value.filter(p => {
    return getLocalDateString(p.created_at) === selectedReportDate.value;
  });
});

const dailyTotalSuccess = computed(() => {
  return dailyPayments.value
    .filter(p => {
      const s = String(p.status).toLowerCase();
      return s === 'succeeded' || s === 'successful' || s === 'paid';
    })
    .reduce((sum, p) => sum + parseFloat(p.amount || 0), 0);
});

const dailyMethodBreakdown = computed(() => {
  const map = {};
  dailyPayments.value.forEach(p => {
    const s = String(p.status).toLowerCase();
    if (s === 'succeeded' || s === 'successful' || s === 'paid') {
      const methodLabel = mapPaymentMethod(p.method, p.reservation);
      map[methodLabel] = (map[methodLabel] || 0) + parseFloat(p.amount || 0);
    }
  });
  return map;
});

// Print execution via isolated hidden iframe
const printReport = () => {
  const printContent = document.getElementById('daily-report-printable');
  if (!printContent) return;

  const iframe = document.createElement('iframe');
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  document.body.appendChild(iframe);

  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Daily Payment Transactions - ${selectedReportDate.value}</title>
  <style>
    @page { size: portrait; margin: 15mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    body { color: #0f172a; padding: 15px; font-size: 12px; line-height: 1.4; }
    .header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
    .serif-font { font-family: Georgia, serif; font-size: 20px; font-weight: 700; color: #0f172a; }
    .summary-cards { display: flex; gap: 12px; margin-bottom: 16px; }
    .summary-cards .card { flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; background: #f8fafc; }
    .card-title { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
    .card-value { font-size: 18px; font-weight: 800; color: #0f172a; }
    .card-value.success { color: #166534; }
    .table-container { width: 100%; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0f172a; color: #ffffff; text-align: left; padding: 8px 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    th.text-end, td.text-end { text-align: right; }
    th.text-center, td.text-center { text-align: center; }
    td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 11px; }
    tr:nth-child(even) td { background-color: #f8fafc; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
    .bg-success-subtle { background: #dcfce7; color: #15803d; }
    .bg-danger-subtle { background: #fee2e2; color: #b91c1c; }
    .bg-warning-subtle { background: #fef9c3; color: #854d0e; }
    .sign-section { margin-top: 35px; display: flex; justify-content: space-between; align-items: flex-end; page-break-inside: avoid; }
    .sign-col { width: 220px; text-align: center; }
    .sign-line { border-bottom: 1px solid #0f172a; height: 35px; margin-bottom: 4px; }
    .sign-caption { font-size: 10px; color: #64748b; }
    .footer { margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 8px; font-size: 9px; color: #94a3b8; text-align: center; }
  </style>
</head>
<body>
  ${printContent.innerHTML}
</body>
</html>`);
  doc.close();

  iframe.contentWindow.focus();
  setTimeout(() => {
    iframe.contentWindow.print();
    setTimeout(() => {
      if (document.body.contains(iframe)) {
        document.body.removeChild(iframe);
      }
    }, 1500);
  }, 250);
};

// Reset to page 1 when searching or filtering
watch([searchQuery, filter, startDate, endDate], () => {
  currentPage.value = 1;
});
</script>

<style scoped>
.btn-white {
  background: white !important;
  color: var(--primary-gold) !important;
  font-weight: 700;
}
.btn-transparent {
  background: transparent !important;
  color: #64748B !important;
  font-weight: 500;
}
.table th {
  background-color: transparent !important;
}
.hover-scale:hover {
  transform: translateY(-1px);
}
</style>
