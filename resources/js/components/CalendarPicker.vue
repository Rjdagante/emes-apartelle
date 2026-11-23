<template>
  <div class="calendar-picker p-2 border rounded-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-2 px-2">
      <button @click="changeMonth(-1)" type="button" class="btn btn-sm btn-light rounded-circle calend-nav-btn">
        <i class="bi bi-chevron-left small"></i>
      </button>
      <span class="fw-bold fs-6 serif-font text-secondary-dark">{{ monthName }}</span>
      <button @click="changeMonth(1)" type="button" class="btn btn-sm btn-light rounded-circle calend-nav-btn">
        <i class="bi bi-chevron-right small"></i>
      </button>
    </div>

    <div class="calendar-grid">
      <div v-for="day in days" :key="day" class="calendar-head">{{ day }}</div>
      <div
        v-for="(dateObj, i) in calendarDays"
        :key="i"
        class="calendar-date"
        :class="{
          'selected': isSelected(dateObj),
          'disabled': isDisabled(dateObj),
          'empty': !dateObj,
          'today': isToday(dateObj),
          'booked': isBooked(dateObj),
          'blocked': isBlocked(dateObj)
        }"
        :title="getDateTitle(dateObj)"
        @click="selectDate(dateObj)"
        @mouseenter="onDateHover(dateObj)"
        @mouseleave="onDateLeave"
      >
        <span v-if="dateObj">{{ dateObj.day }}</span>
      </div>
    </div>

    <!-- Active reason notice (shown on hover or tap) -->
    <div 
      v-if="currentNotice" 
      class="calendar-reason-banner mt-2 p-2 rounded-3 d-flex align-items-center gap-2 animate-fade-in"
      :class="currentNotice.isBlocked ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'"
    >
      <i :class="currentNotice.isBlocked ? 'bi bi-slash-circle-fill text-warning' : 'bi bi-calendar-x-fill text-danger'" class="flex-shrink-0"></i>
      <div class="x-small text-truncate" style="line-height: 1.25;">
        <span class="fw-bold">{{ currentNotice.title }}:</span> {{ currentNotice.text }}
      </div>
    </div>

    <!-- Legend -->
    <div class="calendar-legend d-flex justify-content-center align-items-center gap-3 pt-2 mt-2 border-top" style="font-size: 0.65rem;">
      <div class="d-flex align-items-center gap-1 text-muted">
        <span class="legend-dot bg-danger"></span>
        <span>Booked</span>
      </div>
      <div class="d-flex align-items-center gap-1 text-muted">
        <span class="legend-dot bg-warning"></span>
        <span>Blocked</span>
      </div>
      <div class="d-flex align-items-center gap-1 text-muted">
        <span class="legend-dot bg-gold"></span>
        <span>Selected</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  modelValue: String, // YYYY-MM-DD
  disabledDates: {
    type: Array, // Array of { start: 'YYYY-MM-DD', end: 'YYYY-MM-DD', type: 'blocked'|'booked', reason: '' }
    default: () => []
  },
  minDate: {
    type: String, // YYYY-MM-DD
    default: () => new Date().toISOString().split('T')[0]
  },
  maxDate: {
    type: String,
    default: null
  },
  isCheckout: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const currentViewDate = ref(props.modelValue ? new Date(props.modelValue) : new Date());

// Ensure currentViewDate is valid
if (isNaN(currentViewDate.value.getTime())) {
  currentViewDate.value = new Date();
}

const monthName = computed(() => {
  return currentViewDate.value.toLocaleString('default', { month: 'long', year: 'numeric' });
});

const calendarDays = computed(() => {
  const year = currentViewDate.value.getFullYear();
  const month = currentViewDate.value.getMonth();
  const firstDay = new Date(year, month, 1).getDay();
  const lastDate = new Date(year, month + 1, 0).getDate();

  const d = [];
  // Padding for empty start
  for (let i = 0; i < firstDay; i++) d.push(null);
  // Real days
  for (let i = 1; i <= lastDate; i++) {
    d.push({
      day: i,
      fullDate: new Date(year, month, i)
    });
  }
  return d;
});

const changeMonth = (direction) => {
  const newDate = new Date(currentViewDate.value);
  newDate.setMonth(newDate.getMonth() + direction);
  currentViewDate.value = newDate;
};

const formatToYMD = (date) => {
  if (!date) return '';
  const d = new Date(date);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const hoveredNotice = ref(null);
const clickedNotice = ref(null);

const currentNotice = computed(() => {
  return hoveredNotice.value || clickedNotice.value || null;
});

const parseLocalDate = (dateVal) => {
  if (!dateVal) return null;
  if (dateVal instanceof Date) {
    return new Date(dateVal.getFullYear(), dateVal.getMonth(), dateVal.getDate());
  }
  const str = String(dateVal).trim();
  const match = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (match) {
    return new Date(parseInt(match[1], 10), parseInt(match[2], 10) - 1, parseInt(match[3], 10));
  }
  const d = new Date(str.replace(' ', 'T'));
  return isNaN(d.getTime()) ? null : new Date(d.getFullYear(), d.getMonth(), d.getDate());
};

const getDisabledRange = (dateObj) => {
  if (!dateObj || !props.disabledDates || !props.disabledDates.length) return null;
  const d = parseLocalDate(dateObj.fullDate);
  if (!d) return null;

  return props.disabledDates.find(range => {
    const start = parseLocalDate(range.check_in || range.start || range.start_date);
    const end = parseLocalDate(range.check_out || range.end || range.end_date);
    if (!start || !end) return false;

    // Handle single day range edge-case: if check_in equals check_out, treat end as start + 1 day
    const effectiveEnd = end <= start ? new Date(start.getFullYear(), start.getMonth(), start.getDate() + 1) : end;
    
    if (props.isCheckout) {
      // For check-out, a date is disabled if the night before it is occupied
      return d > start && d <= effectiveEnd;
    } else {
      // For check-in, a date is disabled if the night itself is occupied
      return d >= start && d < effectiveEnd;
    }
  });
};

const isBlocked = (dateObj) => {
  const range = getDisabledRange(dateObj);
  return !!(range && range.type === 'blocked');
};

const isBooked = (dateObj) => {
  const range = getDisabledRange(dateObj);
  return !!(range && range.type !== 'blocked');
};

const isSelected = (dateObj) => {
  if (!dateObj || !props.modelValue) return false;
  return formatToYMD(dateObj.fullDate) === props.modelValue;
};

const isToday = (dateObj) => {
  if (!dateObj) return false;
  return formatToYMD(dateObj.fullDate) === formatToYMD(new Date());
};

const isDisabled = (dateObj) => {
  if (!dateObj) return true;
  const dStr = formatToYMD(dateObj.fullDate);
  
  // Check min date
  if (props.minDate && dStr < props.minDate) return true;
  
  // Check max date
  if (props.maxDate && dStr > props.maxDate) return true;
  
  // Check booked/blocked dates
  return !!getDisabledRange(dateObj);
};

const getDateTitle = (dateObj) => {
  if (!dateObj) return '';
  const dStr = formatToYMD(dateObj.fullDate);
  if (props.minDate && dStr < props.minDate) return 'Past date';
  if (props.maxDate && dStr > props.maxDate) return 'Date unavailable';

  const range = getDisabledRange(dateObj);
  if (range) {
    if (range.type === 'blocked') {
      return range.reason ? `Blocked: ${range.reason}` : 'Blocked by management';
    }
    return 'Already Booked';
  }
  return '';
};

const buildNotice = (dateObj, range) => {
  if (!dateObj || !range) return null;
  const dateStr = dateObj.fullDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  const isBlockedType = range.type === 'blocked';
  return {
    isBlocked: isBlockedType,
    title: isBlockedType ? `${dateStr} Blocked` : `${dateStr} Booked`,
    text: isBlockedType ? (range.reason || 'Unavailable for booking') : 'Reserved by another guest'
  };
};

const selectDate = (dateObj) => {
  if (!dateObj) return;

  if (isDisabled(dateObj)) {
    const range = getDisabledRange(dateObj);
    if (range) {
      clickedNotice.value = buildNotice(dateObj, range);
    }
    return;
  }

  clickedNotice.value = null;
  hoveredNotice.value = null;
  emit('update:modelValue', formatToYMD(dateObj.fullDate));
};

const onDateHover = (dateObj) => {
  if (!dateObj) {
    hoveredNotice.value = null;
    return;
  }
  const range = getDisabledRange(dateObj);
  if (range) {
    hoveredNotice.value = buildNotice(dateObj, range);
  } else {
    hoveredNotice.value = null;
  }
};

const onDateLeave = () => {
  hoveredNotice.value = null;
};

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    const newDate = new Date(newVal);
    if (!isNaN(newDate.getTime())) {
      // Only change view if it's a different month
      if (newDate.getMonth() !== currentViewDate.value.getMonth() || 
          newDate.getFullYear() !== currentViewDate.value.getFullYear()) {
        currentViewDate.value = newDate;
      }
    }
  }
});
</script>

<style scoped>
.calendar-picker {
  min-width: 280px;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.calendar-head {
  text-align: center;
  font-size: 0.65rem;
  font-weight: 800;
  color: #94A3B8; /* Slate 400 */
  padding: 4px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.calendar-date {
  text-align: center;
  padding: 6px 0;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  border-radius: 12px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
  color: #1E293B; /* Slate 800 */
  aspect-ratio: 1 / 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.calendar-date:hover:not(.empty):not(.disabled) {
  background-color: var(--primary-gold-subtle);
  color: var(--primary-gold);
}

.calendar-date.selected {
  background-color: var(--primary-gold) !important;
  color: white !important;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(188, 145, 81, 0.3);
}

.calendar-date.disabled {
  color: #CBD5E1; /* Slate 300 */
  cursor: not-allowed;
  font-weight: 400;
}

.calendar-date.booked {
  color: #F87171 !important;
  background-color: #FEF2F2 !important;
  text-decoration: line-through !important;
  cursor: not-allowed !important;
}

.calendar-date.blocked {
  color: #B45309 !important;
  background-color: #FEF3C7 !important;
  text-decoration: line-through !important;
  cursor: not-allowed !important;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.legend-dot.bg-gold {
  background-color: var(--primary-gold);
}

.calendar-reason-banner {
  transition: all 0.2s ease-in-out;
}

.calendar-date.today:not(.selected):not(.blocked):not(.booked) {
  border: 2px solid var(--primary-gold-subtle);
  color: var(--primary-gold);
}

.calendar-date.empty {
  cursor: default;
}

.calend-nav-btn {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.calend-nav-btn:hover {
  background-color: var(--primary-gold-subtle);
  color: var(--primary-gold);
  transform: scale(1.1);
}
</style>
