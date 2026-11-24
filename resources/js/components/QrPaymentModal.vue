<template>
  <Teleport to="body">
    <div v-if="show" class="qr-modal-backdrop" @click.self="handleClose">
      <div class="qr-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="qrPaymentModalTitle">
        <div class="qr-modal-card rounded-4 shadow-lg bg-white overflow-hidden">
          <!-- Header -->
          <div class="qr-modal-header px-4 py-3 d-flex align-items-center justify-content-between border-bottom">
            <div>
              <h5 id="qrPaymentModalTitle" class="serif-font fw-bold mb-0 text-secondary-dark">
                Complete Payment via GCash
              </h5>
              <small class="text-muted">Scan the QR code below and upload your payment screenshot to confirm your booking.</small>
            </div>
            <button
              type="button"
              class="btn-close shadow-none"
              :disabled="submitting"
              @click="handleClose"
              aria-label="Close"
            ></button>
          </div>

          <!-- Body -->
          <div class="qr-modal-body p-4">
            <!-- Summary Bar -->
            <div class="summary-banner rounded-3 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div>
                <div class="fw-bold text-secondary-dark small">{{ roomLabel }}</div>
                <div class="text-muted" style="font-size: 0.78rem;" v-if="checkIn && checkOut">
                  {{ formatDate(checkIn) }} &rarr; {{ formatDate(checkOut) }}
                </div>
              </div>
              <div class="text-end">
                <span class="d-block text-uppercase fw-bold text-muted" style="font-size: 0.65rem; letter-spacing: 0.8px;">
                  Total Amount Due
                </span>
                <span class="serif-font fw-bold text-gold fs-4">₱{{ formatPrice(amount) }}</span>
              </div>
            </div>

            <div class="row g-4 align-items-stretch">
              <!-- Left: QR Code & GCash Details -->
              <div class="col-md-6">
                <div class="h-100 rounded-4 p-3 gcash-panel text-center d-flex flex-column justify-content-center">
                  <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                    <i class="bi bi-qr-code-scan gcash-blue fs-5"></i>
                    <span class="fw-bold text-uppercase gcash-blue" style="font-size: 0.72rem; letter-spacing: 1px;">
                      Scan to Pay
                    </span>
                  </div>

                  <div v-if="qrUrl" class="qr-image-frame bg-white rounded-3 p-2 mx-auto mb-3 border shadow-sm">
                    <a :href="qrUrl" target="_blank" rel="noopener" title="Open full size QR code">
                      <img :src="qrUrl" alt="GCash QR Code" class="img-fluid qr-img rounded-2">
                    </a>
                  </div>
                  <div v-else class="qr-placeholder rounded-3 p-4 mb-3 bg-white border text-muted small">
                    <i class="bi bi-qr-code fs-1 d-block mb-2 opacity-25"></i>
                    QR code is not set yet. You may send your payment to the GCash number below.
                  </div>

                  <div v-if="gcashNumber" class="mt-auto">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                      <span class="fw-bold text-secondary-dark" style="font-size: 1.05rem; letter-spacing: 0.4px;">
                        {{ gcashNumber }}
                      </span>
                      <button
                        type="button"
                        class="btn btn-sm btn-copy rounded-pill px-2 py-1"
                        @click="copyNumber"
                        :title="copied ? 'Copied!' : 'Copy GCash number'"
                      >
                        <i class="bi" :class="copied ? 'bi-check2 text-success' : 'bi-copy'"></i>
                      </button>
                    </div>
                    <div v-if="gcashAccountName" class="small text-muted mt-1">{{ gcashAccountName }}</div>
                  </div>
                </div>
              </div>

              <!-- Right: Required Payment Screenshot Upload -->
              <div class="col-md-6">
                <div class="h-100 d-flex flex-column">
                  <label class="form-label small fw-bold text-uppercase text-secondary-dark mb-2">
                    Payment Screenshot <span class="text-danger">*</span>
                  </label>

                  <div
                    class="upload-dropzone rounded-4 p-3 flex-grow-1 d-flex flex-column align-items-center justify-content-center text-center"
                    :class="{ 'has-file': previewUrl, 'is-dragging': isDragging }"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleDrop"
                    @click="!previewUrl && triggerFileInput()"
                  >
                    <input
                      ref="fileInputRef"
                      type="file"
                      accept="image/png,image/jpeg,image/webp"
                      class="d-none"
                      @change="handleFileSelect"
                    >

                    <template v-if="previewUrl">
                      <img :src="previewUrl" alt="Payment screenshot preview" class="screenshot-preview rounded-3 mb-2 border">
                      <div class="small fw-semibold text-secondary-dark text-truncate w-100 px-2" :title="selectedFile?.name">
                        {{ selectedFile?.name }}
                      </div>
                      <div class="d-flex gap-2 mt-2">
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                          :disabled="submitting"
                          @click.stop="triggerFileInput"
                        >
                          Change
                        </button>
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-danger rounded-pill px-3"
                          :disabled="submitting"
                          @click.stop="clearFile"
                        >
                          Remove
                        </button>
                      </div>
                    </template>

                    <template v-else>
                      <div class="upload-icon-circle mb-2">
                        <i class="bi bi-cloud-arrow-up fs-3 text-gold"></i>
                      </div>
                      <p class="fw-semibold text-secondary-dark small mb-1">
                        Click to upload or drag &amp; drop
                      </p>
                      <p class="text-muted mb-0" style="font-size: 0.75rem;">
                        PNG, JPG, or WEBP (max 5 MB)
                      </p>
                    </template>
                  </div>

                  <div v-if="errorMsg" class="text-danger small mt-2">
                    <i class="bi bi-exclamation-circle me-1"></i>{{ errorMsg }}
                  </div>
                  <div v-else class="text-muted mt-2" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Upload your payment screenshot to proceed. Our staff will be notified right away to verify your booking.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="qr-modal-footer px-4 py-3 border-top bg-light d-flex justify-content-end gap-2">
            <button
              type="button"
              class="btn btn-outline-secondary rounded-pill px-4 fw-semibold"
              :disabled="submitting"
              @click="handleClose"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn-gold rounded-pill px-4 fw-bold text-uppercase text-white border-0 shadow-sm d-flex align-items-center gap-2"
              :disabled="!selectedFile || submitting"
              @click="handleConfirm"
            >
              <span v-if="submitting" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bi bi-check-circle-fill"></i>
              {{ submitting ? 'Submitting Booking...' : 'Confirm & Submit Booking' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  amount: { type: [Number, String], default: 0 },
  roomLabel: { type: String, default: '' },
  checkIn: { type: String, default: '' },
  checkOut: { type: String, default: '' },
  qrUrl: { type: String, default: null },
  gcashNumber: { type: String, default: '' },
  gcashAccountName: { type: String, default: '' },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const fileInputRef = ref(null);
const selectedFile = ref(null);
const previewUrl = ref('');
const errorMsg = ref('');
const isDragging = ref(false);
const copied = ref(false);

const revokePreview = () => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = '';
  }
};

const clearFile = () => {
  revokePreview();
  selectedFile.value = null;
  errorMsg.value = '';
  if (fileInputRef.value) {
    fileInputRef.value.value = '';
  }
};

watch(() => props.show, (isOpen) => {
  if (!isOpen) {
    clearFile();
  }
});

onBeforeUnmount(() => {
  revokePreview();
});

const triggerFileInput = () => {
  fileInputRef.value?.click();
};

const validateAndSetFile = (file) => {
  errorMsg.value = '';
  if (!file) return;

  if (!file.type.startsWith('image/')) {
    errorMsg.value = 'Please upload a valid image file (JPG, PNG, or WEBP).';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    errorMsg.value = 'Image file size must be 5 MB or smaller.';
    return;
  }

  revokePreview();
  selectedFile.value = file;
  previewUrl.value = URL.createObjectURL(file);
};

const handleFileSelect = (event) => {
  const file = event.target.files?.[0];
  validateAndSetFile(file);
};

const handleDrop = (event) => {
  isDragging.value = false;
  const file = event.dataTransfer?.files?.[0];
  validateAndSetFile(file);
};

const copyNumber = async () => {
  if (!props.gcashNumber) return;
  try {
    await navigator.clipboard.writeText(props.gcashNumber);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
  } catch (err) {
    console.error('Copy failed:', err);
  }
};

const handleClose = () => {
  if (props.submitting) return;
  emit('close');
};

const handleConfirm = () => {
  if (!selectedFile.value || props.submitting) {
    errorMsg.value = 'Please upload your payment screenshot before proceeding.';
    return;
  }
  emit('confirm', selectedFile.value);
};

const formatPrice = (val) => parseFloat(val || 0).toLocaleString();

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<style scoped>
.qr-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1080;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  overflow-y: auto;
}

.qr-modal-dialog {
  width: 100%;
  max-width: 720px;
  margin: auto;
}

.qr-modal-body {
  max-height: 75vh;
  overflow-y: auto;
}

.summary-banner {
  background-color: #fdfbf7;
  border: 1px solid rgba(188, 145, 81, 0.25);
}

.gcash-panel {
  background-color: #f4f9ff;
  border: 1px solid rgba(0, 125, 254, 0.2);
}

.gcash-blue {
  color: #007dfe;
}

.qr-image-frame {
  max-width: 220px;
}

.qr-img {
  max-height: 200px;
  width: auto;
  object-fit: contain;
}

.btn-copy {
  background: white;
  border: 1px solid rgba(0, 125, 254, 0.2);
  color: #007dfe;
  line-height: 1;
}

.btn-copy:hover {
  background: #007dfe;
  color: white;
  border-color: #007dfe;
}

.upload-dropzone {
  border: 2px dashed rgba(188, 145, 81, 0.45);
  background-color: #fdfbf7;
  cursor: pointer;
  min-height: 230px;
  transition: all 0.2s ease;
}

.upload-dropzone:hover,
.upload-dropzone.is-dragging {
  border-color: #BC9151;
  background-color: #faf5ea;
}

.upload-dropzone.has-file {
  border-style: solid;
  border-color: rgba(34, 197, 94, 0.45);
  background-color: #f9fffb;
  cursor: default;
}

.upload-icon-circle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: rgba(188, 145, 81, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
}

.screenshot-preview {
  max-height: 155px;
  max-width: 100%;
  object-fit: contain;
  background: white;
}

.btn-gold {
  background-color: var(--primary-gold, #BC9151) !important;
  color: white !important;
}

.btn-gold:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
