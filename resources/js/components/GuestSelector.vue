<template>
  <div class="guest-selector-wrapper position-relative" ref="wrapperRef">
    <!-- Trigger Input -->
    <button 
      type="button" 
      class="guest-selector-trigger w-100 text-start d-flex justify-content-between align-items-center transition-all"
      :class="[triggerClass, { 'is-active': isOpen }]"
      @click="toggleDropdown"
      :aria-expanded="isOpen"
    >
      <div class="d-flex align-items-center overflow-hidden">
        <i class="bi bi-people text-gold fs-5 flex-shrink-0 me-3"></i>
        <div class="text-truncate">
          <div class="fw-semibold text-secondary-dark text-truncate">{{ displayText }}</div>
          <div v-if="freeChildrenCount > 0" class="x-small text-success fw-bold d-flex align-items-center gap-1 mt-0.5">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ freeChildrenCount }} Child{{ freeChildrenCount > 1 ? 'ren' : '' }} Free (under 7)</span>
          </div>
        </div>
      </div>
      <i class="bi bi-chevron-down text-muted transition-transform flex-shrink-0 ms-2" :class="{ 'rotate-180': isOpen }"></i>
    </button>

    <!-- Dropdown Popover -->
    <transition name="fade-slide">
      <div 
        v-if="isOpen" 
        class="guest-selector-popover position-absolute bg-white rounded-4 shadow-2xl border border-gold-light p-3 p-sm-4"
      >
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-light">
          <div>
            <h6 class="mb-0 fw-bold serif-font text-secondary-dark">Guests & Rooms</h6>
            <span class="x-small text-muted">Select adults and children</span>
          </div>
          <button type="button" class="btn-close small" @click="isOpen = false" aria-label="Close"></button>
        </div>

        <!-- Adults Counter Row -->
        <div class="d-flex justify-content-between align-items-center py-2 mb-2">
          <div>
            <div class="fw-bold text-dark small mb-0">Adults</div>
            <div class="x-small text-muted">Age 18+ (Regular rate)</div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <button 
              type="button" 
              class="btn-counter" 
              :disabled="adults <= minAdults" 
              @click="decrementAdults"
              aria-label="Decrease adults"
            >
              <i class="bi bi-dash"></i>
            </button>
            <span class="counter-val fw-bold text-secondary-dark">{{ adults }}</span>
            <button 
              type="button" 
              class="btn-counter" 
              :disabled="totalGuests >= maxAllowedGuests" 
              @click="incrementAdults"
              aria-label="Increase adults"
            >
              <i class="bi bi-plus"></i>
            </button>
          </div>
        </div>

        <!-- Children Counter Row -->
        <div class="d-flex justify-content-between align-items-center py-2 mb-2 border-top border-light">
          <div>
            <div class="fw-bold text-dark small mb-0">Children</div>
            <div class="x-small text-muted">Ages 0 to 17</div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <button 
              type="button" 
              class="btn-counter" 
              :disabled="children <= 0" 
              @click="decrementChildren"
              aria-label="Decrease children"
            >
              <i class="bi bi-dash"></i>
            </button>
            <span class="counter-val fw-bold text-secondary-dark">{{ children }}</span>
            <button 
              type="button" 
              class="btn-counter" 
              :disabled="totalGuests >= maxAllowedGuests" 
              @click="incrementChildren"
              aria-label="Increase children"
            >
              <i class="bi bi-plus"></i>
            </button>
          </div>
        </div>

        <!-- Child Policy Banner -->
        <div class="child-policy-card alert alert-warning-subtle border-0 rounded-3 p-2.5 mb-3 d-flex align-items-start gap-2">
          <i class="bi bi-info-circle-fill text-gold fs-6 flex-shrink-0 mt-0.5"></i>
          <div class="x-small leading-tight text-secondary-dark">
            <strong>Child Policy:</strong> Children <strong>under 7 years old</strong> stay <strong>FREE of charge</strong>. Children <strong>7 years and older</strong> are charged the standard rate.
          </div>
        </div>

        <!-- Child Ages Section (if children > 0) -->
        <div v-if="children > 0" class="child-ages-container mb-3 pt-2 border-top border-light">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="x-small fw-bold text-uppercase text-muted tracking-wider">Age of each child</span>
            <span class="badge bg-cream text-gold-dark x-small px-2 py-0.5">{{ children }} {{ children === 1 ? 'Child' : 'Children' }}</span>
          </div>
          
          <div class="child-age-list d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
            <div 
              v-for="(age, index) in localChildrenAges" 
              :key="index"
              class="child-age-item p-2 rounded-3 bg-light-cream d-flex justify-content-between align-items-center"
            >
              <span class="small fw-semibold text-secondary-dark">Child {{ index + 1 }}</span>
              <div class="d-flex align-items-center gap-2">
                <select 
                  class="form-select form-select-sm age-select border-gold-light rounded-3 bg-white"
                  :value="age"
                  @change="updateChildAge(index, Number($event.target.value))"
                  style="width: 135px; font-size: 0.8rem;"
                >
                  <option v-for="a in 18" :key="a - 1" :value="a - 1">
                    {{ a - 1 }} {{ a - 1 === 1 ? 'year old' : 'years old' }}
                  </option>
                </select>

                <!-- Free or Charged Badge -->
                <span 
                  v-if="age < 7" 
                  class="badge bg-success-subtle text-success border border-success-subtle x-small px-2 py-1 d-flex align-items-center gap-1"
                  title="Under 7 years old: Stays free of charge"
                >
                  <i class="bi bi-gift-fill"></i> Free
                </span>
                <span 
                  v-else 
                  class="badge bg-warning-subtle text-dark border border-warning-subtle x-small px-2 py-1"
                  title="7 years old and above: Standard adult rate"
                >
                  Adult rate
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Max Capacity Note if approaching max -->
        <div v-if="totalGuests >= maxAllowedGuests" class="x-small text-danger fw-semibold mb-3">
          <i class="bi bi-exclamation-circle-fill me-1"></i>
          Maximum capacity reached ({{ maxAllowedGuests }} guests max for this room).
        </div>

        <!-- Bottom Actions -->
        <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light">
          <div class="x-small text-muted">
            <span class="fw-bold text-dark">{{ chargeableGuests }}</span> paying
            <template v-if="freeChildrenCount > 0">
              &bull; <span class="fw-bold text-success">{{ freeChildrenCount }}</span> free
            </template>
          </div>
          <button 
            type="button" 
            class="btn btn-gold-premium btn-sm px-3 py-1.5 fw-bold rounded-pill text-uppercase x-small"
            @click="isOpen = false"
          >
            Done
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: {
    type: Number,
    default: 1
  },
  adultsValue: {
    type: Number,
    default: 1
  },
  childrenValue: {
    type: Number,
    default: 0
  },
  childrenAgesValue: {
    type: Array,
    default: () => []
  },
  maxOccupancy: {
    type: Number,
    default: 4
  },
  maxExtraGuests: {
    type: Number,
    default: 2
  },
  minOccupancy: {
    type: Number,
    default: 1
  },
  triggerClass: {
    type: String,
    default: 'form-input-premium w-100 px-3 py-2'
  }
});

const emit = defineEmits([
  'update:modelValue',
  'update:adultsValue',
  'update:childrenValue',
  'update:childrenAgesValue',
  'change'
]);

const isOpen = ref(false);
const wrapperRef = ref(null);

const adults = ref(props.adultsValue || props.modelValue || 1);
const children = ref(props.childrenValue || 0);
const localChildrenAges = ref([...props.childrenAgesValue]);

// Sync if props change externally
watch(() => props.adultsValue, (val) => {
  if (val !== undefined && val !== adults.value) adults.value = val;
});
watch(() => props.childrenValue, (val) => {
  if (val !== undefined && val !== children.value) children.value = val;
});
watch(() => props.childrenAgesValue, (val) => {
  if (Array.isArray(val)) {
    localChildrenAges.value = [...val];
  }
}, { deep: true });

// Ensure minAdults respects minOccupancy if specified
const minAdults = computed(() => {
  return 1;
});

const maxAllowedGuests = computed(() => {
  return Number(props.maxOccupancy) || 4;
});

watch(() => props.maxOccupancy, (newMax) => {
  if (newMax) {
    const allowed = Number(newMax);
    if (adults.value > allowed) {
      adults.value = allowed;
      children.value = 0;
      localChildrenAges.value = [];
      emitUpdates();
    } else if (adults.value + children.value > allowed) {
      children.value = Math.max(0, allowed - adults.value);
      localChildrenAges.value = localChildrenAges.value.slice(0, children.value);
      emitUpdates();
    }
  }
});

const totalGuests = computed(() => adults.value + children.value);

const freeChildrenCount = computed(() => {
  return localChildrenAges.value.filter(age => Number(age) < 7).length;
});

const payingChildrenCount = computed(() => {
  return localChildrenAges.value.filter(age => Number(age) >= 7).length;
});

const chargeableGuests = computed(() => {
  return adults.value + payingChildrenCount.value;
});

const displayText = computed(() => {
  const parts = [];
  parts.push(`${adults.value} ${adults.value === 1 ? 'Adult' : 'Adults'}`);
  if (children.value > 0) {
    parts.push(`${children.value} ${children.value === 1 ? 'Child' : 'Children'}`);
  }
  return parts.join(', ');
});

const emitUpdates = () => {
  emit('update:modelValue', totalGuests.value);
  emit('update:adultsValue', adults.value);
  emit('update:childrenValue', children.value);
  emit('update:childrenAgesValue', [...localChildrenAges.value]);
  emit('change', {
    totalGuests: totalGuests.value,
    adults: adults.value,
    children: children.value,
    childrenAges: [...localChildrenAges.value],
    chargeableGuests: chargeableGuests.value,
    freeChildren: freeChildrenCount.value,
    payingChildren: payingChildrenCount.value
  });
};

const incrementAdults = () => {
  if (totalGuests.value < maxAllowedGuests.value) {
    adults.value++;
    emitUpdates();
  }
};

const decrementAdults = () => {
  if (adults.value > minAdults.value) {
    adults.value--;
    emitUpdates();
  }
};

const incrementChildren = () => {
  if (totalGuests.value < maxAllowedGuests.value) {
    children.value++;
    // Default child age: 5 years old (under 7 free)
    localChildrenAges.value.push(5);
    emitUpdates();
  }
};

const decrementChildren = () => {
  if (children.value > 0) {
    children.value--;
    localChildrenAges.value.pop();
    emitUpdates();
  }
};

const updateChildAge = (index, age) => {
  localChildrenAges.value[index] = age;
  emitUpdates();
};

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
};

const handleClickOutside = (e) => {
  if (wrapperRef.value && !wrapperRef.value.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  // Ensure array matches count
  while (localChildrenAges.value.length < children.value) {
    localChildrenAges.value.push(5);
  }
  emitUpdates();
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.guest-selector-popover {
  top: calc(100% + 8px);
  left: 0;
  right: 0;
  min-width: 300px;
  max-height: min(460px, calc(100vh - 80px));
  overflow-y: auto;
  z-index: 1060;
  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.22);
  scrollbar-width: thin;
  scrollbar-color: #d4af37 #f8fafc;
}

.guest-selector-popover::-webkit-scrollbar {
  width: 6px;
}
.guest-selector-popover::-webkit-scrollbar-thumb {
  background-color: #d4af37;
  border-radius: 4px;
}
.guest-selector-popover::-webkit-scrollbar-track {
  background-color: #f8fafc;
}

.guest-selector-trigger {
  cursor: pointer;
  background-color: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  min-height: 48px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.guest-selector-trigger .bi-people {
  margin-right: 0.75rem;
}

.guest-selector-trigger:hover,
.guest-selector-trigger.is-active {
  border-color: #BC9151;
  box-shadow: 0 0 0 3px rgba(188, 145, 81, 0.15);
}

.rotate-180 {
  transform: rotate(180deg);
}

.transition-transform {
  transition: transform 0.2s ease;
}

.btn-counter {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid #d4af37;
  background-color: #fff;
  color: #72531d;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  line-height: 1;
  transition: all 0.15s ease;
  cursor: pointer;
}

.btn-counter:hover:not(:disabled) {
  background-color: #BC9151;
  color: #fff;
  transform: scale(1.05);
}

.btn-counter:disabled {
  border-color: #e2e8f0;
  color: #cbd5e1;
  cursor: not-allowed;
  background-color: #f8fafc;
}

.counter-val {
  min-width: 24px;
  text-align: center;
  font-size: 0.95rem;
}

.bg-light-cream {
  background-color: #FAF8F5;
}

.x-small {
  font-size: 0.72rem;
}

.child-policy-card {
  background-color: rgba(254, 249, 238, 0.9);
  border: 1px solid rgba(234, 179, 8, 0.2) !important;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.2s ease-out;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
