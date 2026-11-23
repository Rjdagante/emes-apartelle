<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Swal from 'sweetalert2';
import PremiumSwal, { confirm as confirmSwal } from '../utils/sweetalert';
import { useAuth } from '../store/auth';
import CalendarPicker from '../components/CalendarPicker.vue';
import GuestSelector from '../components/GuestSelector.vue';

const route = useRoute();
const router = useRouter();
const { state } = useAuth();

const room = ref(null);
const loading = ref(true);
const bookingLoading = ref(false);
const error = ref(null);
const activeImage = ref(-1); // Track active image, -1 is for Main Photo
const systemSettings = ref({ online_booking: true, maintenance_mode: false });
const blockedDates = ref([]);

// Parse guests from query string (e.g. "1 Adult" -> 1)
const parseGuests = (guestsStr) => {
  if (!guestsStr) return 1;
  if (typeof guestsStr === 'number') return guestsStr;
  const match = String(guestsStr).match(/(\d+)/);
  return match ? parseInt(match[1]) : 1;
};

const getInitialGuestName = () => {
  if (state.user?.name) return state.user.name;
  if (state.user?.first_name) {
    return `${state.user.first_name} ${state.user.last_name || ''}`.trim();
  }
  return '';
};

const booking = reactive({
  check_in: route.query.checkIn || '',
  check_out: route.query.checkOut || '',
  guests: parseGuests(route.query.guests),
  adults: route.query.adults ? Number(route.query.adults) : parseGuests(route.query.guests),
  children: route.query.children ? Number(route.query.children) : 0,
  children_ages: route.query.childrenAges 
    ? (typeof route.query.childrenAges === 'string' ? JSON.parse(route.query.childrenAges) : route.query.childrenAges) 
    : [],
  extra_beds: 0,
  guest_name: getInitialGuestName(),
  payment_option: 'full'
});

const freeChildrenCount = computed(() => {
  if (!booking.children_ages || !Array.isArray(booking.children_ages)) return 0;
  return booking.children_ages.filter(age => Number(age) < 7).length;
});

const payingChildrenCount = computed(() => {
  if (!booking.children_ages || !Array.isArray(booking.children_ages)) return 0;
  return booking.children_ages.filter(age => Number(age) >= 7).length;
});

const chargeableGuests = computed(() => {
  return Number(booking.adults || 1) + payingChildrenCount.value;
});

const totalGuests = computed(() => {
  return Number(booking.adults || 1) + Number(booking.children || 0);
});

watch(() => state.user, (newUser) => {
  if (newUser && !booking.guest_name) {
    booking.guest_name = getInitialGuestName();
  }
}, { immediate: true });

const showCheckInCalendar = ref(false);
const showCheckOutCalendar = ref(false);

const toggleCheckIn = () => {
  showCheckInCalendar.value = !showCheckInCalendar.value;
  if (showCheckInCalendar.value) showCheckOutCalendar.value = false;
};

const toggleCheckOut = () => {
  showCheckOutCalendar.value = !showCheckOutCalendar.value;
  if (showCheckOutCalendar.value) showCheckInCalendar.value = false;
};

const todayDate = new Date().toISOString().split('T')[0];

const totalNights = computed(() => {
  if (!booking.check_in || !booking.check_out) return 0;
  const start = new Date(booking.check_in);
  const end = new Date(booking.check_out);
  const diff = end - start;
  const nights = Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
  return nights > 0 ? nights : 0;
});

const maxCheckOutDate = computed(() => {
  if (!booking.check_in || !blockedDates.value.length) return null;
  const checkInDate = new Date(booking.check_in);
  checkInDate.setHours(0, 0, 0, 0);

  let firstNextDate = null;
  blockedDates.value.forEach(range => {
    const start = new Date(range.check_in);
    start.setHours(0, 0, 0, 0);
    if (start >= checkInDate) {
      if (!firstNextDate || start < new Date(firstNextDate)) {
        firstNextDate = range.check_in;
      }
    }
  });

  return firstNextDate ? firstNextDate.split(' ')[0] : null;
});

watch(() => booking.check_in, (newCheckIn) => {
  if (booking.check_out) {
    if (newCheckIn && booking.check_out <= newCheckIn) {
      booking.check_out = '';
    } else if (maxCheckOutDate.value && booking.check_out > maxCheckOutDate.value) {
      booking.check_out = '';
    }
  }
});

const maxExtraBeds = computed(() => {
  if (!room.value) return 0;
  return Number(room.value.max_extra_guests ?? 2);
});

const extraGuestsCount = computed(() => {
  return Number(booking.extra_beds || 0);
});

const extraBedPrice = computed(() => {
  if (!room.value) return 300;
  return Number(room.value.extra_bed_price ?? 300);
});

const incrementExtraBeds = () => {
  if (booking.extra_beds < maxExtraBeds.value) {
    booking.extra_beds++;
  }
};

const decrementExtraBeds = () => {
  if (booking.extra_beds > 0) {
    booking.extra_beds--;
  }
};

const effectiveRate = computed(() => {
  if (!room.value) return 0;
  const isPerHead = room.value.room_type === 'Family Room' || room.value.room_type === 'Barkadahan Room';
  if (room.value.is_promo) {
    if (isPerHead && room.value.promo_price_per_head !== null && Number(room.value.promo_price_per_head) > 0) {
      return Number(room.value.promo_price_per_head);
    }
    if (!isPerHead && room.value.promo_price !== null && Number(room.value.promo_price) > 0) {
      return Number(room.value.promo_price);
    }
  }
  return isPerHead ? Number(room.value.price_per_head || 0) : Number(room.value.price_per_night || 0);
});

const originalRate = computed(() => {
  if (!room.value) return 0;
  const isPerHead = room.value.room_type === 'Family Room' || room.value.room_type === 'Barkadahan Room';
  return isPerHead ? Number(room.value.price_per_head || 0) : Number(room.value.price_per_night || 0);
});

const extraBeddingTotal = computed(() => {
  if (totalNights.value <= 0) return 0;
  return extraGuestsCount.value * extraBedPrice.value * totalNights.value;
});

const basePriceTotal = computed(() => {
  if (!room.value || totalNights.value <= 0) return 0;
  if (room.value.room_type === 'Family Room' || room.value.room_type === 'Barkadahan Room') {
    return totalNights.value * effectiveRate.value * chargeableGuests.value;
  }
  return totalNights.value * effectiveRate.value;
});

const subtotal = computed(() => {
  return basePriceTotal.value + extraBeddingTotal.value;
});

const maxSelectableGuests = computed(() => {
  if (!room.value) return 4;
  return Number(room.value.max_occupancy || 4);
});

const fetchRoom = async () => {
  try {
    loading.value = true;
    const [roomRes, settingsRes, bookedDatesRes] = await Promise.all([
      axios.get(`/api/rooms/${route.params.id}`),
      axios.get('/api/settings/public'),
      axios.get(`/api/rooms/${route.params.id}/booked-dates`)
    ]);
    room.value = roomRes.data;
    systemSettings.value = settingsRes.data;
    blockedDates.value = bookedDatesRes.data;
    
    // Auto adjust booking guests to room capacity and min_occupancy constraints
    if (room.value) {
      const maxOcc = Number(room.value.max_occupancy || 4);
      if (booking.adults > maxOcc) {
        booking.adults = maxOcc;
        booking.children = 0;
        booking.children_ages = [];
      } else if (booking.adults + booking.children > maxOcc) {
        booking.children = Math.max(0, maxOcc - booking.adults);
        booking.children_ages = (booking.children_ages || []).slice(0, booking.children);
      }
      booking.guests = booking.adults + booking.children;

      if (room.value.min_occupancy && booking.guests < room.value.min_occupancy) {
        booking.guests = room.value.min_occupancy;
        booking.adults = Math.max(booking.adults, room.value.min_occupancy);
      }
    }
  } catch (err) {
    console.error("API Fetch Error", err);
    error.value = "We couldn't retrieve the details for this unit. It may have been decommissioned.";
  } finally {
    loading.value = false;
  }
};

const showAllAmenities = ref(false);

const displayedAmenities = computed(() => {
  if (!room.value?.amenities) return [];
  if (showAllAmenities.value) {
    return room.value.amenities;
  }
  return room.value.amenities.slice(0, 6);
});

const formatPrice = (price) => {
  return parseFloat(price || 0).toLocaleString();
};

const getStatusBadge = (status) => {
  switch (status) {
    case 'available': return { class: 'badge-available', text: 'Available' };
    case 'unavailable': return { class: 'badge-unavailable', text: 'Booked' };
    case 'maintenance': return { class: 'badge-maintenance', text: 'Maintenance' };
    default: return { class: 'badge-available', text: 'Available' };
  }
};

const getDefaultImage = (type) => {
  const t = (type || '').toLowerCase();
  if (t.includes('family')) return '/images/unsplash/suite-room.jpg';
  if (t.includes('barkadahan')) return '/images/unsplash/deluxe-room.jpg';
  return '/images/unsplash/standard-room.jpg';
};

const getRoomImage = (room) => {
  if (!room) return '/images/unsplash/standard-room.jpg';
  if (room.image && room.image !== 'null' && room.image !== '') return room.image;
  if (room.images && room.images.length > 0) {
    const first = room.images.find(img => img.image_path && img.image_path !== 'null');
    if (first) return first.image_path;
  }
  return getDefaultImage(room.room_type);
};

const onImageError = (e, roomType) => {
  e.target.src = getDefaultImage(roomType);
};

const handleBooking = async () => {
  if (!booking.check_in || !booking.check_out) {
    Swal.fire({ icon: 'warning', title: 'Dates Required', text: 'Please select both check-in and check-out dates.' });
    return;
  }

  if (totalNights.value <= 0) {
    Swal.fire({ icon: 'warning', title: 'Invalid Dates', text: 'Check-out date must be after check-in date.' });
    return;
  }

  if (room.value && totalGuests.value > Number(room.value.max_occupancy || 4)) {
    Swal.fire({
      icon: 'warning',
      title: 'Capacity Exceeded',
      text: `Maximum capacity for this room is ${room.value.max_occupancy} guests.`,
      confirmButtonColor: '#BC9151'
    });
    return;
  }

  if (!booking.guest_name || !booking.guest_name.trim()) {
    Swal.fire({
      icon: 'warning',
      title: 'Guest Name Required',
      text: 'Please enter the guest full name for this reservation.',
      confirmButtonColor: '#BC9151'
    });
    return;
  }

  if (!state.isAuthenticated) {
    Swal.fire({
      icon: 'info',
      title: 'Sign In Required',
      text: 'Please sign in to complete your booking.',
      showCancelButton: true,
      confirmButtonText: 'Go to Login',
      confirmButtonColor: '#BC9151'
    }).then((result) => {
      if (result.isConfirmed) {
        router.push(`/login?redirect=${route.fullPath}`);
      }
    });
    return;
  }

  try {
    bookingLoading.value = true;
    const response = await axios.post('/api/reservations', {
      room_id: room.value.id,
      check_in: booking.check_in.includes('T') ? booking.check_in : `${booking.check_in}T13:00:00`,
      check_out: booking.check_out.includes('T') ? booking.check_out : `${booking.check_out}T11:00:00`,
      guests: totalGuests.value,
      adults: booking.adults,
      children: booking.children,
      children_ages: booking.children_ages,
      extra_beds: booking.extra_beds,
      guest_name: booking.guest_name.trim(),
      payment_option: 'full'
    });

    const reservationId = response.data?.reservation?.id;
    if (reservationId) {
      router.push({ path: '/booking/success', query: { res_id: reservationId } });
    } else {
      router.push('/booking/success');
    }
  } catch (err) {
    console.error(err);
    Swal.fire({
      icon: 'error',
      title: 'Booking Failed',
      text: err.response?.data?.message || 'Failed to process booking. Please try again.'
    });
  } finally {
    bookingLoading.value = false;
  }
};

const formatDateRange = (start, end) => {
  const options = { month: 'short', day: 'numeric' };
  const startDate = new Date(start).toLocaleDateString('en-US', options);
  const endDate = new Date(end).toLocaleDateString('en-US', options);
  const year = new Date(start).getFullYear();
  return `${startDate} - ${endDate}, ${year}`;
};

const reviewsData = ref({
  average_rating: 0,
  total_reviews: 0,
  star_distribution: { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 },
  reviews: []
});

const fetchReviews = async () => {
  try {
    const response = await axios.get(`/api/rooms/${route.params.id}/reviews`);
    reviewsData.value = response.data;
  } catch (err) {
    console.error("Failed to fetch reviews", err);
  }
};

const formatReviewDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

const getSuggestedReplies = (review) => {
  const guestName = review.user?.name || 'Guest';
  const rating = review.rating || 5;
  
  if (rating >= 5) {
    return [
      `Thank you so much for the wonderful 5-star review, ${guestName}! We are thrilled to hear that you had a great stay at EME's Apartelle. We look forward to welcoming you back soon!`,
      `Hi ${guestName}, thank you for your kind words! We are delighted that you enjoyed our clean and comfortable accommodations. Hope to host you again on your next visit!`,
      `Thank you for choosing EME's Apartelle, ${guestName}! We appreciate your positive feedback and are glad you had a pleasant experience. Safe travels!`
    ];
  } else if (rating === 4) {
    return [
      `Thank you for the 4-star rating, ${guestName}! We're happy you enjoyed your stay and will continue to refine our service to make your next visit a 5-star experience.`,
      `Hi ${guestName}, we appreciate your positive review! Thank you for sharing your feedback with us, and we hope to welcome you back to EME's Apartelle soon.`,
      `Thank you for staying with us, ${guestName}! We are glad you had a good experience and look forward to hosting you again.`
    ];
  } else {
    return [
      `Dear ${guestName}, thank you for your feedback. We sincerely apologize for the aspects of your stay that did not meet your expectations. We are addressing these concerns to improve our service.`,
      `Hi ${guestName}, we appreciate you bringing this to our attention. We apologize for any inconvenience caused during your stay and hope to have the opportunity to provide you with a much better experience in the future.`,
      `Thank you for your review, ${guestName}. We take all guest feedback seriously and are actively working on the areas you highlighted to ensure a more comfortable stay for future guests.`
    ];
  }
};

const handleReplyReview = async (review) => {
  const guestName = review.user?.name || 'Guest';
  const suggestions = getSuggestedReplies(review);
  
  // Build suggestion buttons HTML
  let suggestionsHtml = '';
  suggestions.forEach((suggestion) => {
    // Escape single and double quotes for data-reply
    const escapedSuggestion = suggestion.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    suggestionsHtml += `
      <button type="button" class="btn-quick-reply text-start w-100 p-3 mb-2 rounded-3 border-0 transition-all cursor-pointer" data-reply="${escapedSuggestion}">
        <div class="d-flex align-items-start gap-2">
          <i class="bi bi-chat-text-fill text-gold mt-1" style="font-size: 0.85rem;"></i>
          <span class="quick-reply-text text-muted" style="font-size: 0.8rem; line-height: 1.4; font-weight: 500;">
            ${suggestion}
          </span>
        </div>
      </button>
    `;
  });

  const htmlContent = `
    <div class="text-start">
      <label for="swal-reply-textarea" class="d-block x-small fw-bold text-uppercase text-muted mb-2 tracking-wider">Respond to ${guestName}'s review</label>
      <textarea id="swal-reply-textarea" class="reply-textarea w-100 p-3 mb-3" placeholder="Type your official response here... (Min. 5 characters)">${review.admin_reply || ''}</textarea>
      
      <div class="suggested-replies-container">
        <label class="d-block x-small fw-bold text-uppercase text-gold mb-2 tracking-wider">
          <i class="bi bi-stars me-1"></i> Suggested Replies
        </label>
        <div class="suggested-replies-list">
          ${suggestionsHtml}
        </div>
      </div>
    </div>
  `;

  const { value: replyText } = await PremiumSwal.fire({
    title: review.admin_reply ? 'Edit Response' : 'Reply to Guest',
    html: htmlContent,
    showCancelButton: true,
    confirmButtonText: 'Post Response',
    cancelButtonText: 'Cancel',
    didOpen: (popup) => {
      const textarea = popup.querySelector('#swal-reply-textarea');
      const buttons = popup.querySelectorAll('.btn-quick-reply');
      buttons.forEach(button => {
        button.addEventListener('click', () => {
          const reply = button.getAttribute('data-reply');
          if (textarea) {
            textarea.value = reply;
            textarea.focus();
          }
        });
      });
    },
    preConfirm: () => {
      const textarea = Swal.getPopup().querySelector('#swal-reply-textarea');
      const value = textarea ? textarea.value : '';
      if (!value || value.trim().length < 5) {
        Swal.showValidationMessage('Response must be at least 5 characters long.');
        return false;
      }
      return value;
    }
  });

  if (replyText) {
    try {
      await axios.post(`/api/admin/reviews/${review.id}/reply`, {
        admin_reply: replyText
      });
      
      Swal.fire({
        icon: 'success',
        title: 'Response Posted!',
        text: 'Your official response has been successfully posted.',
        iconColor: '#BC9151',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
      });
      
      fetchReviews(); // Refresh reviews list
    } catch (err) {
      console.error("Failed to post response", err);
      Swal.fire({
        icon: 'error',
        title: 'Posting Failed',
        text: err.response?.data?.message || 'Could not submit your response. Please try again.'
      });
    }
  }
};

const handleDeleteReply = async (review) => {
  const result = await confirmSwal({
    title: 'Delete Response?',
    text: 'Are you sure you want to delete your official response to this review? This action cannot be undone.',
    confirmText: 'Yes, Delete It',
    cancelText: 'Cancel',
    danger: true
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/api/admin/reviews/${review.id}/reply`);
      
      Swal.fire({
        icon: 'success',
        title: 'Response Deleted!',
        text: 'Your official response has been successfully deleted.',
        iconColor: '#BC9151',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
      });
      
      fetchReviews(); // Refresh reviews list
    } catch (err) {
      console.error("Failed to delete response", err);
      Swal.fire({
        icon: 'error',
        title: 'Deletion Failed',
        text: err.response?.data?.message || 'Could not delete your response. Please try again.'
      });
    }
  }
};

onMounted(() => {
  fetchRoom();
  fetchReviews();
});
</script>

<template>
  <div class="room-detail-page bg-cream pb-5">
    <div class="container py-5">
      <!-- Back Link -->
      <router-link to="/rooms" class="back-link animate-fade-up border-0 shadow-sm mb-4">
        <i class="bi bi-chevron-left small fw-bold"></i> Back to Rooms
      </router-link>

      <!-- Loading State -->
      <div v-if="loading" class="loading-state py-5 text-center">
        <div class="spinner-border text-gold" style="width: 3rem; height: 3rem;" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted fw-bold">Loading Room Details...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state py-5 text-center">
        <i class="bi bi-exclamation-octagon fs-1 text-danger mb-3"></i>
        <h3 class="fw-bold">{{ error }}</h3>
        <router-link to="/rooms" class="btn btn-gold rounded-pill px-4 mt-3">Back to Rooms</router-link>
      </div>

      <!-- Room Content -->
      <div v-else-if="room" class="row g-5 mt-2">
        <!-- Left Column - Room Details -->
        <div class="col-lg-7 animate-fade-up">
          <!-- Main Image and Gallery -->
          <div class="room-gallery-container mb-4">
            <div class="room-gallery rounded-5 overflow-hidden shadow-gold-lg position-relative mb-3">
              <img :src="activeImage === -1 ? getRoomImage(room) : (room.images[activeImage]?.image_path || getRoomImage(room))" :alt="room.room_type" class="main-image w-100 h-100 object-fit-cover transition-all" @error="onImageError($event, room.room_type)">
              
              <div class="gallery-overlay p-4 d-flex align-items-end">
                  <span class="status-badge px-4 py-2" :class="getStatusBadge(room.status).class">
                    <i class="bi bi-stars me-2"></i>
                    {{ getStatusBadge(room.status).text }}
                  </span>
              </div>
            </div>

            <!-- Thumbnails if multiple images exist -->
            <div v-if="room.images && room.images.length > 0" class="gallery-thumbnails d-flex gap-2 overflow-x-auto pb-2 custom-scrollbar">
               <!-- Main Image Thumbnail -->
               <div class="thumb-wrapper rounded-4 overflow-hidden flex-shrink-0 cursor-pointer border-3 transition-all p-1"
                    :class="activeImage === -1 ? 'border-gold shadow-gold-sm opacity-100' : 'border-light-subtle opacity-60 hover-opacity-100'"
                    style="width: 100px; height: 75px;"
                    @click="activeImage = -1">
                  <img :src="getRoomImage(room)" class="w-100 h-100 object-fit-cover rounded-3" @error="onImageError($event, room.room_type)">
               </div>

               <div v-for="(img, idx) in room.images" :key="img.id" 
                    class="thumb-wrapper rounded-4 overflow-hidden flex-shrink-0 cursor-pointer border-3 transition-all p-1"
                    :class="activeImage === idx ? 'border-gold shadow-gold-sm opacity-100' : 'border-light-subtle opacity-60 hover-opacity-100'"
                    style="width: 100px; height: 75px;"
                    @click="activeImage = idx">
                  <img :src="img.image_path" class="w-100 h-100 object-fit-cover rounded-3" @error="onImageError($event, room.room_type)">
               </div>
            </div>
          </div>

          <!-- Room Info Header -->
          <div class="room-info mb-5">
             <div class="d-flex align-items-center gap-2 mb-3">
                 <span class="badge bg-gold-subtle text-gold text-uppercase tracking-widest px-3 py-2 small fw-bold">{{ room.room_type }}</span>
                 <span class="text-muted small fw-bold" v-if="room.room_size"><i class="bi bi-arrows-fullscreen me-1"></i> {{ room.room_size }} m²</span>
             </div>
             <h1 class="room-title serif-font display-5 fw-bold mb-3 text-secondary-dark">Room #{{ room.room_number }}</h1>
             
             <div class="room-specs-row d-flex gap-3 flex-wrap">
                 <div class="spec-item d-flex align-items-center gap-2 bg-white px-3 py-2 rounded-3 shadow-sm">
                    <i class="bi bi-people-fill text-gold fs-5"></i>
                    <span class="small fw-bold text-dark" v-if="room.min_occupancy && room.min_occupancy > 1">{{ room.min_occupancy }} to {{ room.max_occupancy }} Guests</span>
                    <span class="small fw-bold text-dark" v-else>Up to {{ room.max_occupancy }} Guests</span>
                 </div>
                 <div class="spec-item d-flex align-items-center gap-2 bg-white px-3 py-2 rounded-3 shadow-sm" v-if="room.bed_type">
                    <i class="bi bi-door-open-fill text-gold fs-5"></i>
                    <span class="small fw-bold text-dark">{{ room.bed_type }}</span>
                 </div>
                 <div class="spec-item d-flex align-items-center gap-2 bg-white px-3 py-2 rounded-3 shadow-sm">
                    <i class="bi bi-award-fill text-gold fs-5"></i>
                    <span class="small fw-bold text-dark">Verified Quality</span>
                 </div>
             </div>
          </div>

          <!-- Description -->
          <div class="room-section mb-4">
            <h4 class="section-title serif-font fw-bold mb-3 text-secondary-dark">Room Details</h4>
            <p class="room-description text-muted lh-lg">{{ room.description || 'Enjoy a comfortable stay in this well-appointed room. We offer a blend of modern comfort and warm hospitality.' }}</p>
          </div>

          <!-- Amenities (Real from DB) -->
          <div class="room-section mb-4">
            <h4 class="section-title serif-font fw-bold mb-4 text-secondary-dark">Amenities Included</h4>
            <div v-if="room.amenities && room.amenities.length > 0">
              <div class="amenities-grid-modern">
                <div v-for="amenity in displayedAmenities" :key="amenity.id" class="amenity-card-detail p-3 rounded-4 bg-white shadow-sm d-flex align-items-center gap-3 transition-all">
                  <div class="amenity-icon-box-detail rounded-circle shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background-color: var(--primary-gold); color: white;">
                    <i :class="amenity.icon.startsWith('bi-') ? ['bi', amenity.icon] : ['bi', `bi-${amenity.icon}`]"></i>
                  </div>
                  <div>
                    <h6 class="mb-0 fw-bold text-dark small">{{ amenity.name }}</h6>
                  </div>
                </div>
              </div>
              
              <button 
                v-if="room.amenities.length > 6" 
                @click="showAllAmenities = !showAllAmenities" 
                class="btn btn-link text-gold p-0 mt-4 small fw-bold text-decoration-none d-inline-flex align-items-center gap-1 shadow-none"
                style="font-size: 0.8rem; border: none; background: transparent;"
              >
                <template v-if="showAllAmenities">
                  <span>Hide amenities</span> <i class="bi bi-chevron-up"></i>
                </template>
                <template v-else>
                  <span>View all amenities (+{{ room.amenities.length - 6 }})</span> <i class="bi bi-chevron-down"></i>
                </template>
              </button>
            </div>
            <div v-else class="p-5 bg-white-glass rounded-4 text-center border border-dashed border-gold">
               <i class="bi bi-stars fs-1 text-gold opacity-50 mb-2 d-block"></i>
               <p class="text-muted mb-0">Standard amenities included with this room.</p>
            </div>
          </div>

          <!-- Guest Reviews Section -->
          <div class="room-section mt-5 border-top pt-5">
            <h4 class="section-title serif-font fw-bold mb-4 text-secondary-dark d-flex align-items-center gap-2">
              <i class="bi bi-chat-left-heart-fill text-gold"></i>
              Guest Reviews & Feedback
            </h4>

            <div class="row g-4 mb-4 align-items-stretch">
              <!-- Rating Score Overview -->
              <div class="col-md-5 d-flex">
                <div class="rating-overview-card w-100 p-4 rounded-4 bg-white shadow-sm border text-center d-flex flex-column justify-content-center align-items-center">
                  <span class="x-small text-muted text-uppercase fw-bold tracking-widest mb-1">Average Rating</span>
                  <div class="display-3 fw-bold text-secondary-dark serif-font mb-2">{{ reviewsData.average_rating }}</div>
                  
                  <!-- Stars Row -->
                  <div class="stars-row text-gold fs-5 mb-2 d-flex gap-1">
                    <i v-for="star in 5" :key="star" :class="star <= Math.round(reviewsData.average_rating) ? 'bi bi-star-fill text-warning' : 'bi bi-star text-muted'"></i>
                  </div>
                  
                  <span class="small text-muted fw-bold">Based on {{ reviewsData.total_reviews }} reviews</span>
                </div>
              </div>

              <!-- Rating Star Distribution -->
              <div class="col-md-7 d-flex">
                <div class="rating-distribution-card w-100 p-4 rounded-4 bg-white shadow-sm border d-flex flex-column justify-content-between">
                  <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="d-flex align-items-center gap-3">
                    <span class="small fw-bold text-muted" style="min-width: 48px;">{{ star }} Star</span>
                    <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px;">
                      <div 
                        class="progress-bar bg-gold rounded-pill" 
                        role="progressbar" 
                        :style="{ width: reviewsData.total_reviews > 0 ? ((reviewsData.star_distribution[star] || 0) / reviewsData.total_reviews * 100) + '%' : '0%' }"
                        aria-valuenow="0" 
                        aria-valuemin="0" 
                        aria-valuemax="100"
                      ></div>
                    </div>
                    <span class="small text-muted fw-bold text-end" style="min-width: 32px;">
                      {{ reviewsData.star_distribution[star] || 0 }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reviews List -->
            <div class="reviews-list-container d-flex flex-column gap-3 mt-4" v-if="reviewsData.reviews.length > 0">
              <div v-for="review in reviewsData.reviews" :key="review.id" class="review-item-card p-4 rounded-4 bg-white shadow-sm border animate-fade-in">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md rounded-circle bg-gold-subtle text-gold d-flex align-items-center justify-content-center fw-bold overflow-hidden" style="width: 40px; height: 40px;">
                      <img v-if="review.user?.profile_photo_url" :src="review.user.profile_photo_url" :alt="review.user?.name" class="w-100 h-100 object-fit-cover">
                      <span v-else>{{ (review.user?.name || 'G').charAt(0) }}</span>
                    </div>
                    <div>
                      <h6 class="fw-bold text-secondary-dark mb-0 small">{{ review.user?.name || 'Guest' }}</h6>
                      <small class="text-muted x-small">{{ formatReviewDate(review.created_at) }}</small>
                    </div>
                  </div>
                  <div class="text-gold small d-flex gap-1">
                    <i v-for="star in 5" :key="star" :class="star <= review.rating ? 'bi bi-star-fill text-warning' : 'bi bi-star text-muted'"></i>
                  </div>
                </div>
                <p class="review-comment text-muted mb-0 small lh-lg font-italic">"{{ review.comment }}"</p>

                <!-- Admin Reply if exists -->
                <div v-if="review.admin_reply" class="admin-reply-card mt-3 p-3 rounded-3 bg-light border-start border-gold border-3 animate-fade-in text-start">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center bg-white border border-light-subtle" style="width: 24px; height: 24px;">
                        <img src="/images/eme-logo.jpg" alt="EME's Apartelle Logo" class="w-100 h-100 object-fit-cover">
                      </div>
                      <span class="x-small fw-bold text-secondary-dark text-uppercase tracking-wider">Response from EME's Apartelle</span>
                    </div>
                    <div v-if="state.user?.role === 'admin' || state.user?.role === 'staff'" class="d-flex align-items-center gap-2">
                      <button 
                        class="btn btn-link text-gold p-0 x-small fw-bold text-decoration-none shadow-none"
                        @click="handleReplyReview(review)"
                      >
                        <i class="bi bi-pencil-square"></i> Edit
                      </button>
                      <span class="text-muted x-small">|</span>
                      <button 
                        class="btn btn-link text-danger p-0 x-small fw-bold text-decoration-none shadow-none"
                        @click="handleDeleteReply(review)"
                      >
                        <i class="bi bi-trash"></i> Delete
                      </button>
                    </div>
                  </div>
                  <p class="mb-0 text-muted x-small lh-base font-italic">"{{ review.admin_reply }}"</p>
                </div>

                <!-- Admin action to reply -->
                <div v-if="!review.admin_reply && (state.user?.role === 'admin' || state.user?.role === 'staff')" class="mt-3 text-end animate-fade-in">
                  <button 
                    class="btn btn-outline-gold btn-sm rounded-pill px-3 fw-bold x-small text-uppercase"
                    @click="handleReplyReview(review)"
                  >
                    <i class="bi bi-reply-fill"></i> Reply to Guest
                  </button>
                </div>
              </div>
            </div>

            <!-- Empty Reviews State -->
            <div v-else class="p-5 bg-white rounded-4 text-center border shadow-sm mt-4">
              <div class="p-3 bg-gold-lightest d-inline-flex rounded-circle mb-3">
                 <i class="bi bi-chat-left-quote text-gold display-6"></i>
              </div>
              <h5 class="fw-bold mb-2">No Reviews Yet</h5>
              <p class="text-muted small mb-0">Be the first to share your experience! Review this room in the "My Bookings" page after your stay.</p>
            </div>
          </div>
        </div>

        <!-- Right Column - Booking Sidebar -->
        <div class="col-lg-5 animate-fade-up" style="animation-delay: 0.2s">
          <div class="booking-sidebar sticky-top" style="top: 90px; z-index: 100;">
            <div class="booking-card-premium rounded-4 bg-white shadow-gold-xl border-0">
               <div class="booking-card-header-premium p-4 pb-2 bg-white flex-shrink-0">
                  <h5 class="mb-1 serif-font fw-bold text-dark">Book This Room</h5>
                  <p class="x-small text-muted mb-0 tracking-wider text-uppercase fw-bold">Instant Confirmation</p>
               </div>
               
               <div class="booking-card-body-premium p-4 pt-3">
                  <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                     <div>
                        <span class="d-block x-small text-muted text-uppercase fw-bold tracking-widest mb-1">
                          {{ (room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? 'Price per Head / Night' : 'Price per Night' }}
                        </span>
                        <div class="h4 mb-0 fw-bold text-dark serif-font d-flex align-items-baseline gap-2">
                          <template v-if="room.is_promo">
                            <div><span class="text-gold">₱</span>{{ formatPrice(effectiveRate) }}</div>
                            <span class="text-muted text-decoration-line-through fs-6 fw-normal">₱{{ formatPrice(originalRate) }}</span>
                          </template>
                          <template v-else>
                            <div><span class="text-gold">₱</span>{{ formatPrice(originalRate) }}</div>
                          </template>
                        </div>
                     </div>
                     <div class="text-end">
                        <span v-if="room.is_promo" class="badge bg-danger text-white px-3 py-2 rounded-pill small fw-bold">
                           <i class="bi bi-tag-fill me-1"></i> {{ room.promo_title || 'Promo Special' }}
                        </span>
                        <span v-else class="badge bg-success-subtle text-success px-3 py-2 rounded-pill small fw-bold">
                           <i class="bi bi-clock-history me-1"></i> Best Rate
                        </span>
                     </div>
                  </div>

                  <!-- Booking Form -->
                  <form @submit.prevent="handleBooking">
                      <div class="col-12 mb-3">
                        <label class="form-label x-small fw-bold text-uppercase text-muted mb-2 tracking-wider">Select Dates</label>
                        <div class="row g-2">
                          <div class="col-6">
                            <span class="d-block xx-small text-muted text-uppercase fw-bold mb-1">Check-in</span>
                            <div class="position-relative">
                              <div @click="toggleCheckIn" 
                                   class="form-input-premium w-100 p-2 text-center cursor-pointer d-flex align-items-center justify-content-center gap-2"
                                   :class="{'border-gold shadow-gold-sm': showCheckInCalendar}">
                                <i class="bi bi-calendar-date text-gold"></i>
                                <span>{{ booking.check_in || 'mm/dd/yyyy' }}</span>
                              </div>
                              <div v-if="showCheckInCalendar" class="calendar-popup position-absolute start-0 mt-2 z-3 animate-fade-in shadow-lg">
                                <CalendarPicker 
                                  v-model="booking.check_in" 
                                  :min-date="todayDate" 
                                  :disabled-dates="blockedDates"
                                  @update:modelValue="showCheckInCalendar = false"
                                />
                              </div>
                            </div>
                          </div>
                          <div class="col-6">
                            <span class="d-block xx-small text-muted text-uppercase fw-bold mb-1">Check-out</span>
                            <div class="position-relative">
                              <div @click="toggleCheckOut" 
                                   class="form-input-premium w-100 p-2 text-center cursor-pointer d-flex align-items-center justify-content-center gap-2"
                                   :class="{'border-gold shadow-gold-sm': showCheckOutCalendar}">
                                <i class="bi bi-calendar-check text-gold"></i>
                                <span>{{ booking.check_out || 'mm/dd/yyyy' }}</span>
                              </div>
                              <div v-if="showCheckOutCalendar" class="calendar-popup position-absolute end-0 mt-2 z-3 animate-fade-in shadow-lg">
                                <CalendarPicker 
                                  v-model="booking.check_out" 
                                  :min-date="booking.check_in || todayDate" 
                                  :max-date="maxCheckOutDate"
                                  :disabled-dates="blockedDates"
                                  :is-checkout="true"
                                  @update:modelValue="showCheckOutCalendar = false"
                                />
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 mt-2">
                        <label class="form-label admin-label mb-1 small fw-bold text-muted text-uppercase tracking-wider">Number of Guests</label>
                        <GuestSelector 
                          v-model="booking.guests"
                          v-model:adultsValue="booking.adults"
                          v-model:childrenValue="booking.children"
                          v-model:childrenAgesValue="booking.children_ages"
                          :maxOccupancy="Number(room.max_occupancy || 4)"
                          :minOccupancy="Number(room.min_occupancy || 1)"
                        />
                      </div>

                      <!-- Extra Bed Option -->
                      <div v-if="maxExtraBeds > 0" class="col-12 mt-3">
                        <label class="form-label admin-label mb-1 small fw-bold text-muted text-uppercase tracking-wider">Extra Bed (Optional)</label>
                        <div class="extra-bed-box form-input-premium w-100 px-3 py-2 d-flex align-items-center justify-content-between gap-2">
                          <div class="d-flex align-items-center gap-2 overflow-hidden">
                            <i class="bi bi-plus-circle text-gold fs-5 flex-shrink-0"></i>
                            <div class="text-truncate">
                              <div class="fw-semibold text-dark small text-truncate">
                                {{ booking.extra_beds > 0 ? `${booking.extra_beds} Extra Bed${booking.extra_beds > 1 ? 's' : ''}` : 'Add Extra Bed' }}
                              </div>
                              <div class="xx-small text-muted">
                                ₱{{ formatPrice(extraBedPrice) }} / night · Max {{ maxExtraBeds }}
                              </div>
                            </div>
                          </div>
                          <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <button
                              type="button"
                              class="btn-extra-bed-step"
                              :disabled="booking.extra_beds <= 0"
                              @click="decrementExtraBeds"
                              aria-label="Decrease extra beds"
                            >
                              <i class="bi bi-dash"></i>
                            </button>
                            <span class="extra-bed-count fw-bold text-dark">{{ booking.extra_beds }}</span>
                            <button
                              type="button"
                              class="btn-extra-bed-step"
                              :disabled="booking.extra_beds >= maxExtraBeds"
                              @click="incrementExtraBeds"
                              aria-label="Increase extra beds"
                            >
                              <i class="bi bi-plus"></i>
                            </button>
                          </div>
                        </div>
                      </div>

                      <!-- Guest Full Name Input -->
                      <div class="col-12 mt-3 mb-3">
                        <label class="form-label admin-label mb-1 small fw-bold text-muted text-uppercase tracking-wider">Guest Full Name</label>
                        <input 
                          type="text" 
                          class="form-input-premium w-100 px-3 py-2" 
                          v-model="booking.guest_name" 
                          placeholder="Enter guest full name"
                          autocomplete="name"
                          required
                        >
                      </div>
                    
                    <!-- Financial Summary -->
                    <div v-if="totalNights > 0" class="financial-summary mb-4 p-4 rounded-4 bg-gold-lightest border border-gold-light">
                      <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small fw-semibold">
                          <template v-if="room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room'">
                            ((₱{{ formatPrice(effectiveRate) }} × {{ chargeableGuests }} paying guest{{ chargeableGuests > 1 ? 's' : '' }}) × {{ totalNights }} night{{ totalNights > 1 ? 's' : '' }})
                          </template>
                          <template v-else>
                            (₱{{ formatPrice(effectiveRate) }} × {{ totalNights }} night{{ totalNights > 1 ? 's' : '' }})
                          </template>
                          <span v-if="room.is_promo" class="badge bg-danger text-white ms-1 x-small py-0 px-1">PROMO</span>
                        </span>
                        <span class="text-dark small fw-bold">₱{{ formatPrice(basePriceTotal) }}</span>
                      </div>
                      
                      <!-- Free Children (Under 7) Line Item -->
                      <div v-if="freeChildrenCount > 0" class="d-flex justify-content-between mb-2 text-success">
                        <span class="small fw-semibold d-flex align-items-center gap-1">
                          <i class="bi bi-gift-fill"></i>
                          Children under 7 ({{ freeChildrenCount }} child{{ freeChildrenCount > 1 ? 'ren' : '' }})
                        </span>
                        <span class="small fw-bold">FREE (₱0)</span>
                      </div>
                      
                      <!-- Additional Beddings Line Item if applicable -->
                      <div v-if="extraGuestsCount > 0" class="d-flex justify-content-between mb-2">
                        <span class="text-gold-dark small fw-semibold">
                          <i class="bi bi-plus-circle me-1"></i>
                          Extra Bed ({{ extraGuestsCount }} bed{{ extraGuestsCount > 1 ? 's' : '' }} × ₱{{ formatPrice(extraBedPrice) }} × {{ totalNights }} night{{ totalNights > 1 ? 's' : '' }})
                        </span>
                        <span class="text-gold-dark small fw-bold">+₱{{ formatPrice(extraBeddingTotal) }}</span>
                      </div>

                      <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small fw-semibold">Service & Tourism Fee</span>
                        <span class="text-success small fw-bold">INCLUDED</span>
                      </div>
                      <div class="d-flex justify-content-between pt-3 border-top border-gold-light">
                        <span class="h5 mb-0 fw-bold text-dark serif-font">
                          Total Amount
                        </span>
                        <span class="h5 mb-0 fw-bold text-gold serif-font">
                          ₱{{ formatPrice(subtotal) }}
                        </span>
                      </div>
                      <div class="text-muted xx-small text-end mt-1">
                        Settle at the apartelle upon check-in
                      </div>
                    </div>

                    <div v-else class="text-center p-4 rounded-4 bg-light border border-dashed mb-4">
                      <p class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i> Enter your dates to calculate your stay</p>
                    </div>

                    <button 
                      type="submit" 
                      class="btn btn-gold btn-lg w-100 py-3 rounded-pill fw-bold text-uppercase tracking-widest shadow-gold transition-all"
                      :disabled="room.status !== 'available' || bookingLoading || !systemSettings.online_booking || systemSettings.maintenance_mode"
                    >
                      <span v-if="bookingLoading" class="spinner-border spinner-border-sm me-2"></span>
                      <template v-if="systemSettings.maintenance_mode">Maintenance</template>
                      <template v-else-if="!systemSettings.online_booking">Online Disabled</template>
                      <template v-else>
                        {{ room.status === 'available' ? 'Book Now' : 'Unavailable' }}
                      </template>
                    </button>
                  </form>

                  <!-- Booked Dates Section -->
                  <div v-if="blockedDates && blockedDates.length > 0" class="booked-dates-section mt-4 animate-fade-up">
                    <div class="d-flex align-items-center gap-2 mb-3">
                      <div class="p-1 bg-danger-subtle rounded-circle">
                         <i class="bi bi-calendar-x text-danger small"></i>
                      </div>
                      <h6 class="mb-0 fw-bold text-dark small text-uppercase tracking-wider">Unavailable Dates</h6>
                    </div>
                    <div class="booked-dates-list p-3 rounded-4 bg-light border">
                      <div v-for="(res, idx) in blockedDates" :key="idx" class="booked-date-item d-flex justify-content-between align-items-center mb-2 last-mb-0">
                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                           <span class="dot-indicator flex-shrink-0" :class="res.type === 'blocked' ? 'bg-warning' : 'bg-danger'"></span>
                           <div class="text-truncate">
                             <span class="small fw-semibold text-secondary-dark d-block">{{ formatDateRange(res.check_in, res.check_out) }}</span>
                             <small v-if="res.reason" class="text-warning-emphasis fw-bold x-small d-block text-truncate">
                               <i class="bi bi-info-circle me-1"></i>Reason: {{ res.reason }}
                             </small>
                             <small v-else-if="res.type === 'blocked'" class="text-muted x-small d-block text-truncate">
                               <i class="bi bi-slash-circle me-1"></i>Blocked by administration
                             </small>
                           </div>
                        </div>
                        <span 
                          class="badge rounded-pill bg-white x-small fw-bold text-uppercase px-2 py-1 border flex-shrink-0 ms-2"
                          :class="res.type === 'blocked' ? 'text-warning border-warning' : 'text-danger border-danger-subtle'"
                        >
                          {{ res.type === 'blocked' ? 'Blocked' : 'Booked' }}
                        </span>
                      </div>
                    </div>
                    <p class="x-small text-muted mt-2 text-center"><i class="bi bi-info-circle me-1"></i> These dates are currently unavailable for booking.</p>
                  </div>

                  <div class="trust-footer mt-4 pt-3 border-top text-center d-flex justify-content-center gap-4">
                     <span class="xx-small text-muted fw-bold"><i class="bi bi-shield-check text-success me-1"></i> SECURE SSL</span>
                     <span class="xx-small text-muted fw-bold"><i class="bi bi-credit-card text-gold me-1"></i> NO BOOKING FEES</span>
                  </div>
               </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.room-detail-page {
  min-height: 100vh;
  padding-top: 100px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  color: var(--secondary-dark);
  text-decoration: none;
  font-weight: 700;
  padding: 0.75rem 1.5rem;
  background: white;
  border-radius: 50px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.back-link:hover {
  color: var(--primary-gold);
  transform: translateX(-5px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.08) !important;
}

/* Room Gallery */
.room-gallery-container {
    perspective: 1000px;
}

.room-gallery {
  height: 420px;
  background-color: #f8fafc;
}

.main-image {
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease;
}

.gallery-thumbnails {
    padding: 0.5rem 0.25rem;
}

.thumb-wrapper {
    background: white;
}

.border-gold {
    border-color: var(--primary-gold) !important;
}

.shadow-gold-sm {
    box-shadow: 0 4px 12px rgba(188, 145, 81, 0.25);
}

.hover-opacity-100:hover {
    opacity: 1 !important;
}

.border-light-subtle {
    border-color: #f1f5f9 !important;
}

.shadow-gold-lg {
    box-shadow: 0 20px 50px rgba(188, 145, 81, 0.15);
}

.gallery-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(15, 23, 42, 0.6), transparent 40%);
}

.status-badge {
  border-radius: 50px;
  font-size: 0.9rem;
  font-weight: 800;
  text-transform: uppercase;
  backdrop-filter: blur(10px);
}

.badge-available { background: #10b981 !important; color: #ffffff !important; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.35); }
.badge-unavailable { background: #ef4444 !important; color: #ffffff !important; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.35); }
.badge-maintenance { background: #f59e0b !important; color: #ffffff !important; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35); }

/* Amenities Grid Detail */
.amenities-grid-modern {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
}

.amenity-card-detail:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important;
}

.bg-gold-subtle { background-color: rgba(188, 145, 81, 0.1); }
.bg-gold-lightest { background-color: rgba(188, 145, 81, 0.03); }
.border-gold-light { border-color: rgba(188, 145, 81, 0.1) !important; }

/* Form Elements Premium */
.form-input-premium {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s;
    outline: none;
}

.form-input-premium:focus {
    background: white;
    border-color: var(--primary-gold);
    box-shadow: 0 0 0 4px rgba(188, 145, 81, 0.1);
}

.shadow-gold-xl {
    box-shadow: 0 30px 60px -12px rgba(188, 145, 81, 0.2);
}

/* Booking Sidebar Card & Scrollbar */
.booking-card-premium {
  max-height: calc(100vh - 110px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.booking-card-header-premium {
  border-bottom: 1px solid #f1f5f9;
}

.booking-card-body-premium {
  overflow-y: auto;
  overflow-x: hidden;
  flex-grow: 1;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: var(--primary-gold-light) #f8fafc;
}

.booking-card-body-premium::-webkit-scrollbar {
  width: 6px;
}

.booking-card-body-premium::-webkit-scrollbar-track {
  background: #f8fafc;
  border-radius: 8px;
  margin: 6px 0;
}

.booking-card-body-premium::-webkit-scrollbar-thumb {
  background: var(--primary-gold-light);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.4);
}

.booking-card-body-premium::-webkit-scrollbar-thumb:hover {
  background: var(--primary-gold);
}

.x-small { font-size: 0.75rem; }
.xx-small { font-size: 0.65rem; }

.cursor-pointer { cursor: pointer; }

.calendar-popup {
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    min-width: 280px;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-out forwards;
}

@media (max-width: 991.98px) {
  .room-gallery { height: 400px; }
  .room-title { font-size: 2.5rem; }
  .booking-card-premium {
    max-height: none;
    overflow: visible;
  }
  .booking-card-body-premium {
    overflow-y: visible;
  }
}

@media (max-width: 767.98px) {
  .room-gallery { height: 300px; }
  .amenities-grid-modern { grid-template-columns: 1fr; }
}
.dot-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  display: inline-block;
}

.last-mb-0:last-child {
  margin-bottom: 0 !important;
}

.booked-dates-list {
  max-height: 200px;
  overflow-y: auto;
}

.booked-dates-list::-webkit-scrollbar {
  width: 4px;
}

.booked-dates-list::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
.btn-outline-gold {
  border: 1px solid var(--primary-gold) !important;
  color: var(--primary-gold) !important;
  background-color: transparent !important;
  transition: all 0.3s ease;
}
.btn-outline-gold:hover {
  background-color: var(--primary-gold) !important;
  color: white !important;
}
.border-dashed {
  border-top-style: dashed !important;
  border-top-width: 1px !important;
  border-top-color: rgba(188, 145, 81, 0.2) !important;
}

/* Suggested Replies styling inside Swal */
:deep(.reply-container) {
  text-align: left;
}

:deep(.reply-textarea) {
  width: 100%;
  height: 120px;
  padding: 12px 16px;
  border-radius: 12px;
  border: 1px solid rgba(188, 145, 81, 0.3);
  background-color: #ffffff;
  font-family: var(--font-sans);
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--text-dark);
  outline: none;
  transition: all var(--transition-normal);
}

:deep(.reply-textarea:focus) {
  border-color: var(--primary-gold);
  box-shadow: 0 0 0 4px var(--primary-gold-subtle);
}

:deep(.suggested-title) {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--primary-gold);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-top: 1rem;
  margin-bottom: 0.75rem;
}

:deep(.suggested-replies-list) {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 220px;
  overflow-y: auto;
  padding-right: 4px;
}

:deep(.btn-quick-reply) {
  background-color: #ffffff !important;
  border: 1px solid rgba(0, 0, 0, 0.06) !important;
  border-left: 3px solid var(--primary-gold) !important;
  border-radius: 8px !important;
  padding: 10px 14px !important;
  text-align: left !important;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
  cursor: pointer;
  width: 100%;
}

:deep(.btn-quick-reply:hover) {
  background-color: var(--bg-cream) !important;
  border-color: var(--primary-gold) !important;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(188, 145, 81, 0.08);
}

:deep(.btn-quick-reply:active) {
  transform: scale(0.99);
}

:deep(.quick-reply-text) {
  font-size: 0.8rem;
  line-height: 1.4;
  color: var(--text-muted);
  font-weight: 500;
  display: block;
}

:deep(.btn-quick-reply:hover .quick-reply-text) {
  color: var(--text-dark);
}

.extra-bed-box {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  min-height: 54px;
}

.btn-extra-bed-step {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: 1px solid #d4b886;
  background: #fff;
  color: #bc9151;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  font-size: 1rem;
  transition: all 0.2s ease;
}

.btn-extra-bed-step:hover:not(:disabled) {
  background: #bc9151;
  border-color: #bc9151;
  color: #fff;
}

.btn-extra-bed-step:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  border-color: #cbd5e1;
  color: #94a3b8;
}

.extra-bed-count {
  min-width: 20px;
  text-align: center;
  font-size: 0.9rem;
}
</style>
