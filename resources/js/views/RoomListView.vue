<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const rooms = ref([]);
const packages = ref([]);
const selectedGuestPackage = ref(null);
const showGuestPackageModal = ref(false);
const loading = ref(true);
const error = ref(null);
const filterType = ref('all');
const systemSettings = ref({
    maintenance_mode: false,
    online_booking: true
});

const fetchRooms = async () => {
    loading.value = true;
    try {
        const [roomsRes, settingsRes, packagesRes] = await Promise.all([
            axios.get('/api/rooms'),
            axios.get('/api/settings/public'),
            axios.get('/api/room-packages/public').catch(() => ({ data: [] }))
        ]);
        rooms.value = sortRoomsByNumber(roomsRes.data);
        systemSettings.value = settingsRes.data;
        packages.value = packagesRes.data || [];
    } catch (err) {
        console.error("API Error:", err);
        error.value = "Failed to load rooms. Please try again later.";
    } finally {
        loading.value = false;
    }
};

const sortRoomsByNumber = (list) => {
    if (!Array.isArray(list)) return [];
    return [...list].sort((a, b) => {
        return String(a.room_number || '').localeCompare(String(b.room_number || ''), undefined, {
            numeric: true,
            sensitivity: 'base'
        });
    });
};

// Dynamically get unique room types for filtering
const hasPromoRooms = computed(() => {
    return rooms.value.some(r => r.is_promo);
});

const roomTypes = computed(() => {
    const types = new Set(rooms.value.map(r => r.room_type));
    const list = ['all'];
    if (hasPromoRooms.value) {
        list.push('promo');
    }
    if (packages.value.length > 0) {
        list.push('packages');
    }
    return [...list, ...Array.from(types)];
});

const filteredRooms = computed(() => {
    let list = rooms.value;
    if (filterType.value === 'promo') {
        list = list.filter(room => room.is_promo);
    } else if (filterType.value !== 'all') {
        list = list.filter(room => room.room_type === filterType.value);
    }
    return sortRoomsByNumber(list);
});

const formatPrice = (price) => {
    return parseFloat(price || 0).toLocaleString();
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'available': return { class: 'badge-available', text: 'Available', icon: 'bi-check-circle-fill' };
        case 'unavailable': return { class: 'badge-unavailable', text: 'Booked', icon: 'bi-x-circle-fill' };
        case 'maintenance': return { class: 'badge-maintenance', text: 'Maintenance', icon: 'bi-tools' };
        default: return { class: 'badge-available', text: 'Available', icon: 'bi-check-circle-fill' };
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

const formatIconClass = (icon) => {
  if (!icon) return '';
  return icon.startsWith('bi-') ? icon : `bi-${icon}`;
};

const getPackageImage = (pkg) => {
    if (!pkg) return '/images/unsplash/hero-resort.jpg';
    if (pkg.image_url) return pkg.image_url;
    if (pkg.image) {
        if (pkg.image.startsWith('http') || pkg.image.startsWith('/')) return pkg.image;
        return `/storage/${pkg.image}`;
    }
    return '/images/unsplash/hero-resort.jpg';
};

const formatDurationShort = (duration) => {
    if (!duration) return '3D, 2N';
    const d = duration.trim();
    if (/3\s*days?.*2\s*nights?/i.test(d) || /2\s*nights?.*3\s*days?/i.test(d)) return '3D, 2N';
    if (/2\s*nights?.*1\s*day/i.test(d)) return '3D, 2N';
    if (/(\d+)\s*days?.*(\d+)\s*nights?/i.test(d)) {
        const m = d.match(/(\d+)\s*days?.*(\d+)\s*nights?/i);
        return `${m[1]}D, ${m[2]}N`;
    }
    if (/(\d+)\s*nights?.*(\d+)\s*days?/i.test(d)) {
        const m = d.match(/(\d+)\s*nights?.*(\d+)\s*days?/i);
        return `${m[2]}D, ${m[1]}N`;
    }
    if (/(\d+)\s*nights?/i.test(d)) {
        const m = d.match(/(\d+)\s*nights?/i);
        return `${m[1]} Nights`;
    }
    if (/(\d+)\s*days?/i.test(d)) {
        const m = d.match(/(\d+)\s*days?/i);
        return `${m[1]} Days`;
    }
    return d.length > 12 ? d.slice(0, 10) + '...' : d;
};

const openGuestPackageModal = (pkg) => {
    selectedGuestPackage.value = pkg;
    showGuestPackageModal.value = true;
};

const closeGuestPackageModal = () => {
    showGuestPackageModal.value = false;
    selectedGuestPackage.value = null;
};

const bookPackageOnline = (pkg) => {
    if (!pkg) return;
    closeGuestPackageModal();
    router.push({
        path: '/book-now',
        query: {
            packageId: pkg.id,
            roomId: pkg.room_id || ''
        }
    });
};

onMounted(fetchRooms);
</script>

<template>
  <div class="rooms-page">
    <section class="page-header contrast-overlay">
      <div class="container position-relative z-1 text-center animate-fade-up">
        <div class="designer-label mx-auto mb-3 justify-content-center">
          <span class="label-dot bg-white"></span>
          <span class="label-text text-white">EME'S APARTELLE</span>
          <span class="label-dot bg-white"></span>
        </div>
        <h1 class="display-3 fw-bold serif-font mb-4 text-white">Our Rooms</h1>
        <p class="section-description mx-auto text-white-50 fs-5 mb-0" style="max-width: 700px;">
          Explore our comfortable rooms designed for your stay.
        </p>
      </div>
    </section>

    <!-- Rooms Content -->
    <section class="rooms-content py-7 bg-cream">
      <div class="container">
        <!-- Dynamic Filter Tabs -->
        <div class="filter-tabs animate-fade-up delay-1 mb-5" v-if="roomTypes.length > 1">
          <button 
            v-for="type in roomTypes"
            :key="type"
            class="filter-tab" 
            :class="{ 
              'active': filterType === type,
              'filter-tab-promo': type === 'promo',
              'filter-tab-packages': type === 'packages'
            }" 
            @click="filterType = type"
          >
            <template v-if="type === 'all'">All Rooms</template>
            <template v-else-if="type === 'promo'">
              <i class="bi bi-tag-fill me-1.5"></i> Promo Deals
            </template>
            <template v-else-if="type === 'packages'">
              <i class="bi bi-box2-heart-fill me-1.5"></i> Tour Packages
            </template>
            <template v-else>{{ type }}</template>
          </button>
        </div>

        <!-- Skeleton Loading State -->
        <div v-if="loading" class="row g-3 g-lg-4">
          <div v-for="n in 8" :key="'skel-' + n" class="col-xl-3 col-lg-3 col-md-6 d-flex flex-column">
            <div class="room-card skeleton-card border-0 w-100 h-100 d-flex flex-column">
              <div class="skeleton-image skeleton-shimmer"></div>
              <div class="room-card-body d-flex flex-column flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="skeleton-line skeleton-shimmer" style="width: 60%; height: 10px;"></div>
                  <div class="skeleton-line skeleton-shimmer" style="width: 25%; height: 10px;"></div>
                </div>
                <div class="skeleton-line skeleton-shimmer mb-3" style="width: 55%; height: 18px;"></div>
                <div class="d-flex align-items-baseline gap-2 mb-3">
                  <div class="skeleton-line skeleton-shimmer" style="width: 15%; height: 14px;"></div>
                  <div class="skeleton-line skeleton-shimmer" style="width: 30%; height: 22px;"></div>
                  <div class="skeleton-line skeleton-shimmer" style="width: 20%; height: 10px;"></div>
                </div>
                <div class="d-flex gap-2 mb-3">
                  <div class="skeleton-pill skeleton-shimmer"></div>
                  <div class="skeleton-pill skeleton-shimmer"></div>
                </div>
                <div class="skeleton-line skeleton-shimmer mb-2" style="width: 100%; height: 10px;"></div>
                <div class="skeleton-line skeleton-shimmer mb-4" style="width: 75%; height: 10px;"></div>
                <div class="skeleton-btn skeleton-shimmer mt-auto"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state glass-panel py-7 rounded-5 border-danger d-flex flex-column align-items-center text-center">
          <i class="bi bi-exclamation-triangle fs-1 text-danger mb-3"></i>
          <p class="h5 serif-font">{{ error }}</p>
          <button class="btn btn-gold rounded-pill px-4 mt-3" @click="fetchRooms">Retry Connection</button>
        </div>

        <!-- Maintenance Mode Alert -->
        <div v-else-if="systemSettings.maintenance_mode" class="maintenance-state glass-panel py-7 rounded-5 text-center mb-5 animate-fade-up">
          <div class="mb-4">
            <i class="bi bi-tools fs-1 text-gold opacity-50"></i>
          </div>
          <h2 class="serif-font mb-3">System Maintenance</h2>
          <p class="text-muted mb-4 max-w-600 mx-auto">We're currently performing scheduled maintenance to improve your experience. Online reservations are temporarily unavailable. Please contact us via phone for urgent bookings.</p>
          <router-link to="/contact" class="btn btn-gold rounded-pill px-5 py-2 fw-bold text-uppercase">Contact Support</router-link>
        </div>

        <!-- Online Booking Disabled Notice -->
        <div v-if="!systemSettings.online_booking && !systemSettings.maintenance_mode" class="alert alert-warning rounded-4 border-0 shadow-sm p-4 mb-5 animate-fade-up d-flex align-items-center gap-3">
           <i class="bi bi-calendar-x fs-3"></i>
           <div>
              <h6 class="mb-1 fw-bold">Online Reservations are Currently Restricted</h6>
              <p class="mb-0 small opacity-75">You can still browse our rooms, but online booking is temporarily disabled. Please reach out to our staff to reserve a room.</p>
           </div>
        </div>

        <!-- Tour Packages Grid (Balanced 4-Column Layout) -->
        <div v-else-if="filterType === 'packages'" class="row g-3 g-lg-4">
          <div 
            v-for="(pkg, index) in packages" 
            :key="pkg.id" 
            class="col-xl-3 col-lg-3 col-md-6 animate-fade-up d-flex flex-column"
            :style="{ animationDelay: `${index * 0.1}s` }"
          >
            <div class="room-card card-hover shadow-gold-sm border-0 w-100 h-100 d-flex flex-column">
              <div class="room-card-image position-relative">
                <img :src="getPackageImage(pkg)" :alt="pkg.name" loading="lazy" class="w-100 h-100 object-fit-cover">
                <div class="room-overlay"></div>
                <span class="room-badge badge-available">
                  <i class="bi bi-compass-fill me-1"></i>
                  {{ pkg.tour_type || 'Tour Package' }}
                </span>
                <span class="promo-badge badge bg-gold text-white shadow-sm" :title="pkg.duration || '3D, 2N'">
                  <i class="bi bi-clock-history me-1"></i>
                  <span class="text-truncate d-inline-block" style="max-width: 100px; vertical-align: bottom;">
                    {{ formatDurationShort(pkg.duration) }}
                  </span>
                </span>
                <div class="room-size-tag" v-if="pkg.room_type_label || pkg.room">
                   <i class="bi bi-door-closed-fill small me-1"></i>
                   {{ pkg.room ? `Room #${pkg.room.room_number}` : pkg.room_type_label }}
                </div>
              </div>

              <div class="room-card-body d-flex flex-column flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="room-type-label text-gold">{{ pkg.room_type_label || 'Tour Package' }}</span>
                  <span class="small text-muted fw-semibold" v-if="pkg.pax"><i class="bi bi-people-fill me-1"></i>{{ pkg.pax }}</span>
                </div>
                
                <h4 class="room-card-title serif-font mb-1 text-truncate" :title="pkg.name">{{ pkg.name }}</h4>
                <p class="text-muted small mb-2 text-truncate" v-if="pkg.tagline" :title="pkg.tagline">{{ pkg.tagline }}</p>
                
                <div class="room-card-price mb-3">
                  <span class="currency text-gold">₱</span>
                  <span class="price text-gold">{{ formatPrice(pkg.price) }}</span>
                  <span class="period">/ package</span>
                </div>
                
                <!-- Inclusion highlights (Clean, uniform 2-row pills) -->
                <div class="room-features mb-3">
                  <div class="feature-pill" v-for="dest in (pkg.tour_destinations || []).slice(0, 2)" :key="dest" :title="dest">
                    <span>🏝️ {{ dest }}</span>
                  </div>
                  <div class="feature-pill" v-if="(pkg.tour_destinations || []).length > 2" :title="(pkg.tour_destinations || []).slice(2).join(', ')">
                    <span>+{{ (pkg.tour_destinations || []).length - 2 }} more</span>
                  </div>
                  <div class="feature-pill" v-if="(pkg.tour_inclusions || []).length > 0" title="Includes Lunch">
                    <span>🍱 Lunch Included</span>
                  </div>
                  <template v-if="(!pkg.tour_destinations || pkg.tour_destinations.length === 0) && pkg.room?.amenities">
                    <div class="feature-pill" v-for="amenity in pkg.room.amenities.slice(0, 2)" :key="amenity.id" :title="amenity.name">
                      <i :class="['bi', formatIconClass(amenity.icon)]"></i>
                      <span>{{ amenity.name }}</span>
                    </div>
                  </template>
                </div>
                
                <div class="mt-auto pt-2 d-flex gap-2">
                  <button 
                    type="button" 
                    class="btn btn-gold flex-grow-1 py-2 rounded-pill fw-bold text-uppercase d-flex align-items-center justify-content-center gap-1.5 shadow-sm transition-all"
                    style="font-size: 0.75rem;"
                    @click="bookPackageOnline(pkg)"
                  >
                    <i class="bi bi-calendar-check"></i>
                    Book
                  </button>
                  <button 
                    type="button" 
                    class="btn btn-gold-outline px-3 py-2 rounded-pill fw-bold text-uppercase d-flex align-items-center justify-content-center gap-1 transition-all"
                    style="font-size: 0.75rem;"
                    @click="openGuestPackageModal(pkg)"
                    title="View Inclusions"
                  >
                    <i class="bi bi-info-circle"></i> Details
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredRooms.length === 0" class="empty-state glass-panel py-7 rounded-5 d-flex flex-column align-items-center text-center">
          <div class="mb-4">
            <i class="bi bi-search fs-1 text-muted opacity-50"></i>
          </div>
          <h3 class="serif-font mb-3">No Rooms Found</h3>
          <p class="text-muted mb-4">Try selecting a different category or check back later.</p>
          <button class="btn btn-gold rounded-pill px-5 py-2 fw-bold text-uppercase" @click="filterType = 'all'">View All Rooms</button>
        </div>

        <!-- Rooms Grid -->
        <div v-else class="row g-3 g-lg-4">
          <div 
            v-for="(room, index) in filteredRooms" 
            :key="room.id" 
            class="col-xl-3 col-lg-3 col-md-6 animate-fade-up d-flex flex-column"
            :style="{ animationDelay: `${index * 0.1}s` }"
          >
            <div class="room-card card-hover shadow-gold-sm border-0 w-100 h-100 d-flex flex-column">
              <div class="room-card-image">
                <img :src="getRoomImage(room)" :alt="room.room_type" loading="lazy" @error="onImageError($event, room.room_type)">
                <div class="room-overlay"></div>
                
                <!-- Status Badge -->
                <span class="room-badge" :class="getStatusBadge(room.status).class">
                  <i :class="getStatusBadge(room.status).icon" class="me-1"></i>
                  {{ getStatusBadge(room.status).text }}
                </span>

                <!-- Special Promo Badge (Shortened & Compact to prevent collision) -->
                <span v-if="room.is_promo" class="promo-badge badge bg-danger text-white shadow-sm" :title="room.promo_title || 'Special Promo'">
                  <i class="bi bi-tag-fill me-1"></i>
                  <span class="text-truncate d-inline-block" style="max-width: 110px; vertical-align: bottom;">
                    {{ room.promo_title ? room.promo_title.replace(/promotion/i, 'Promo') : (room.promo_discount_percent ? `${room.promo_discount_percent}% OFF` : 'SPECIAL PROMO') }}
                  </span>
                </span>

                <div class="room-size-tag" v-if="room.room_size">
                   <i class="bi bi-arrows-fullscreen small me-1"></i>
                   {{ room.room_size }} m²
                </div>
              </div>

              <div class="room-card-body d-flex flex-column flex-grow-1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="room-type-label text-gold">{{ room.room_type }}</span>
                    <div class="room-bed-type" v-if="room.bed_type">
                        <i class="bi bi-door-open-fill small me-1 text-muted"></i>
                        <span class="small text-muted fw-semibold">{{ room.bed_type }}</span>
                    </div>
                </div>
                
                <h4 class="room-card-title serif-font">Room #{{ room.room_number }}</h4>
                
                <div class="room-card-price mb-3">
                  <template v-if="room.is_promo">
                    <div class="d-flex align-items-baseline gap-2 flex-wrap">
                      <span class="text-muted text-decoration-line-through small">
                        ₱{{ formatPrice((room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? room.price_per_head : room.price_per_night) }}
                      </span>
                      <span class="currency text-gold">₱</span>
                      <span class="price text-gold">
                        {{ formatPrice((room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? (room.promo_price_per_head || room.price_per_head) : (room.promo_price || room.price_per_night)) }}
                      </span>
                      <span class="period">
                        {{ (room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? '/ head / night' : '/ night' }}
                      </span>
                    </div>
                  </template>
                  <template v-else>
                    <span class="currency">₱</span>
                    <span class="price">
                      {{ formatPrice((room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? room.price_per_head : room.price_per_night) }}
                    </span>
                    <span class="period">
                      {{ (room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? '/ head / night' : '/ night' }}
                    </span>
                  </template>
                </div>
                
                <!-- Inclusion Icons (At most 2 key amenities + counter so cards stay 2 rows max) -->
                <div class="room-features mb-3">
                  <div class="feature-pill" title="Occupancy">
                    <i class="bi bi-people-fill"></i> 
                    <span v-if="room.min_occupancy && room.min_occupancy > 1">{{ room.min_occupancy }} to {{ room.max_occupancy }} Guests</span>
                    <span v-else>{{ room.max_occupancy }} Guests</span>
                  </div>
                  <div class="feature-pill" v-for="amenity in (room.amenities ? room.amenities.slice(0, 2) : [])" :key="amenity.id" :title="amenity.name">
                    <i :class="['bi', formatIconClass(amenity.icon)]"></i>
                    <span>{{ amenity.name }}</span>
                  </div>
                  <div class="feature-pill" v-if="room.amenities && room.amenities.length > 2" :title="room.amenities.slice(2).map(a => a.name).join(', ')">
                    <span>+{{ room.amenities.length - 2 }} more</span>
                  </div>
                </div>
                
                <p v-if="room.description" class="room-card-description mb-3 text-truncate-2">{{ room.description }}</p>
                
                <!-- Uniform Bottom-Pinned Action Button -->
                <div class="mt-auto pt-2">
                  <router-link 
                    :to="{ name: 'room-detail', params: { id: room.id }}" 
                    class="btn btn-gold-outline w-100 py-2.5 rounded-pill fw-bold text-uppercase d-flex align-items-center justify-content-center gap-2 transition-all small"
                    :class="{ 'op-50 pointer-none': room.status !== 'available' || systemSettings.maintenance_mode }"
                  >
                    {{ (room.status === 'available' && !systemSettings.maintenance_mode) ? 'View Details' : 'Not Available' }}
                    <i class="bi bi-chevron-right small"></i>
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- GUEST PACKAGE DETAILS MODAL -->
    <div v-if="showGuestPackageModal && selectedGuestPackage" class="modal-overlay d-flex align-items-center justify-content-center p-3 p-md-4 animate-fade-in" style="position: fixed; inset: 0; background: rgba(15,23,42,0.75); backdrop-filter: blur(8px); z-index: 10000; overflow-y: auto;" @click.self="closeGuestPackageModal">
      <div class="bg-white rounded-5 shadow-2xl w-100 d-flex flex-column overflow-hidden my-auto border" style="max-width: 860px; max-height: 90vh;">
        <!-- Hero Header with High-Contrast Visible Text & Spacious Padding -->
        <div class="position-relative flex-shrink-0" style="height: 220px; background: #0f172a;">
          <img :src="getPackageImage(selectedGuestPackage)" class="w-100 h-100 object-fit-cover" style="opacity: 0.55;">
          <!-- Multi-stop dark gradient for guaranteed text readability -->
          <div class="position-absolute inset-0" style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.4) 0%, rgba(15, 23, 42, 0.75) 50%, rgba(15, 23, 42, 0.96) 100%);"></div>
          
          <!-- Close Button -->
          <div class="position-absolute top-0 end-0 p-3 p-md-4">
            <button 
              class="btn btn-dark bg-dark bg-opacity-75 text-white rounded-circle p-2 d-flex align-items-center justify-content-center border border-white border-opacity-25 shadow-sm" 
              style="width: 40px; height: 40px; backdrop-filter: blur(10px);" 
              @click="closeGuestPackageModal" 
              title="Close"
            >
              <i class="bi bi-x-lg fs-6"></i>
            </button>
          </div>
          
          <!-- Header Content (Pill tags + Large White Title + Tagline) -->
          <div class="position-absolute bottom-0 start-0 p-4 p-md-4 text-white w-100" style="padding-bottom: 1.5rem !important;">
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
              <span class="badge px-3 py-1.5 rounded-pill x-small fw-bold text-uppercase tracking-wider shadow-sm" style="background-color: #bc9151 !important; color: #ffffff !important;">
                Exclusive Tour &amp; Stay Package
              </span>
              <span class="badge px-3 py-1.5 rounded-pill x-small fw-bold shadow-sm" style="background-color: #ffffff !important; color: #0f172a !important; border: 1px solid rgba(255,255,255,0.4);">
                <i class="bi bi-clock-history me-1 text-gold"></i>{{ selectedGuestPackage.duration || '3 Days, 2 Nights' }}
              </span>
              <span class="badge px-3 py-1.5 rounded-pill x-small fw-bold shadow-sm" style="background-color: rgba(255,255,255,0.2) !important; color: #ffffff !important; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);">
                <i class="bi bi-people-fill me-1"></i>{{ selectedGuestPackage.pax || 'Good for 2 pax' }}
              </span>
            </div>
            
            <h2 class="serif-font fw-bold mb-1 text-white fs-2" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9); letter-spacing: -0.5px;">
              {{ selectedGuestPackage.name }}
            </h2>
            <p class="mb-0 text-white small fw-semibold" style="text-shadow: 0 1px 4px rgba(0,0,0,0.95); opacity: 0.95;" v-if="selectedGuestPackage.tagline">
              <i class="bi bi-geo-alt-fill text-gold me-1"></i>{{ selectedGuestPackage.tagline }}
            </p>
          </div>
        </div>

        <!-- Modal Body with Comfortable, Generous Padding (No cramming!) -->
        <div class="p-4 p-md-4 overflow-y-auto flex-grow-1 d-flex flex-column gap-4 bg-cream-soft">
          <!-- Rate & Highlights Bar (Spacious inside, clear text) -->
          <div class="p-3.5 p-md-4 rounded-4 bg-white border d-flex flex-wrap justify-content-between align-items-center gap-3 shadow-xs">
            <div>
              <span class="text-muted xx-small fw-bold text-uppercase tracking-wider d-block mb-1">Package Total Rate</span>
              <div class="d-flex align-items-baseline gap-2">
                <span class="h2 fw-bold text-gold serif-font mb-0" style="color: #bc9151 !important;">₱{{ formatPrice(selectedGuestPackage.price) }}</span>
                <span class="small text-muted fw-semibold">/ all-inclusive total</span>
              </div>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge bg-light text-secondary-dark border px-3.5 py-2 rounded-pill small fw-bold">
                <i class="bi bi-people-fill text-gold me-1.5"></i>{{ selectedGuestPackage.pax || 'Good for 2 pax' }}
              </span>
              <span class="badge bg-light text-secondary-dark border px-3.5 py-2 rounded-pill small fw-bold">
                <i class="bi bi-compass-fill text-info me-1.5"></i>{{ selectedGuestPackage.tour_type || 'Joiners Tour' }}
              </span>
            </div>
          </div>

          <!-- Description paragraph with ample breathing room -->
          <div v-if="selectedGuestPackage.description" class="px-2">
            <p class="text-secondary-dark small mb-0" style="line-height: 1.7; font-size: 0.9rem;">
              {{ selectedGuestPackage.description }}
            </p>
          </div>

          <!-- LANDSCAPE ROOM ACCOMMODATION CARD (Generous Padding & Spacing) -->
          <div class="p-4 rounded-4 bg-white border shadow-xs">
            <div class="row g-4 align-items-center">
              <!-- Left: Landscape Room Picture (Clean & Spacious) -->
              <div class="col-md-5 col-lg-5">
                <div class="rounded-3 overflow-hidden position-relative shadow-xs bg-light" style="height: 190px;">
                  <img 
                    :src="getRoomImage(selectedGuestPackage.room)" 
                    :alt="selectedGuestPackage.room?.room_type || 'Room'"
                    class="w-100 h-100 object-fit-cover transition-all"
                    @error="onImageError($event, selectedGuestPackage.room?.room_type)"
                  >
                  <!-- Clean Floating Pill Badge in Corner -->
                  <span class="position-absolute top-0 start-0 m-2.5 badge bg-dark bg-opacity-80 text-white shadow-sm px-3 py-1.5 rounded-pill x-small fw-bold border border-white border-opacity-25 backdrop-blur">
                    <i class="bi bi-door-closed-fill me-1 text-gold"></i>
                    {{ selectedGuestPackage.room ? `Room #${selectedGuestPackage.room.room_number}` : 'Private Room' }}
                  </span>
                </div>
              </div>

              <!-- Right: Room Details & Structured 2-Column Amenities List -->
              <div class="col-md-7 col-lg-7 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex justify-content-between align-items-start mb-1 flex-wrap gap-2">
                    <div>
                      <span class="text-gold xx-small fw-bold text-uppercase tracking-wider d-block mb-1" style="color: #bc9151 !important;">Inclusive Accommodation</span>
                      <h4 class="serif-font fw-bold text-secondary-dark mb-1" style="color: #0f172a !important;">
                        {{ selectedGuestPackage.room ? `${selectedGuestPackage.room.room_type} (Room #${selectedGuestPackage.room.room_number})` : (selectedGuestPackage.room_type_label || 'Private Room Accommodation') }}
                      </h4>
                    </div>
                    <router-link 
                      v-if="selectedGuestPackage.room?.id" 
                      :to="'/rooms/' + selectedGuestPackage.room.id" 
                      class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 x-small fw-bold d-inline-flex align-items-center gap-1.5"
                      title="View room photos and specifications"
                    >
                      <span>View Room</span>
                      <i class="bi bi-arrow-right"></i>
                    </router-link>
                  </div>

                  <!-- Room Specs Badges (Spacious pills with comfortable gaps & margins) -->
                  <div class="d-flex flex-wrap align-items-center gap-2 my-2.5">
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
                      <i class="bi bi-people-fill text-gold"></i>
                      <span>Max {{ selectedGuestPackage.room?.max_occupancy || 4 }} Guests</span>
                    </span>
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" v-if="selectedGuestPackage.room?.bed_type">
                      <i class="bi bi-lamp-fill text-gold"></i>
                      <span>{{ selectedGuestPackage.room.bed_type }}</span>
                    </span>
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" v-if="selectedGuestPackage.room?.room_size">
                      <i class="bi bi-aspect-ratio text-gold"></i>
                      <span>{{ selectedGuestPackage.room.room_size }} sq.m</span>
                    </span>
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
                      <i class="bi bi-shield-check text-gold"></i>
                      <span>Private En-Suite</span>
                    </span>
                  </div>
                </div>

                <!-- Structured Amenities (Spacious 2-Column Grid with Generous Padding) -->
                <div class="pt-3.5 mt-2 border-top">
                  <div class="x-small fw-bold text-muted text-uppercase tracking-wider mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-stars text-gold fs-6"></i>
                    <span>Room Amenities &amp; Inclusions</span>
                  </div>

                  <div class="row g-3" v-if="selectedGuestPackage.room?.amenities && selectedGuestPackage.room.amenities.length > 0">
                    <div 
                      v-for="amenity in selectedGuestPackage.room.amenities" 
                      :key="amenity.id"
                      class="col-6 col-sm-6"
                    >
                      <div class="d-flex align-items-center gap-2 py-1 px-1">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 26px; height: 26px; background: rgba(188, 145, 81, 0.12); color: #bc9151;">
                          <i :class="['bi', formatIconClass(amenity.icon) || 'bi-check-circle-fill']" style="font-size: 0.9rem;"></i>
                        </div>
                        <span class="fw-semibold text-secondary-dark small text-truncate" style="color: #1e293b !important; font-size: 0.85rem;">
                          {{ amenity.name }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Fallback if no amenities loaded -->
                  <div class="row g-3" v-else>
                    <div 
                      v-for="(inc, idx) in selectedGuestPackage.room_inclusions" 
                      :key="idx"
                      class="col-6 col-sm-6"
                    >
                      <div class="d-flex align-items-center gap-2 py-1 px-1">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 26px; height: 26px; background: rgba(188, 145, 81, 0.12); color: #bc9151;">
                          <i class="bi bi-check2" style="font-size: 0.95rem;"></i>
                        </div>
                        <span class="fw-semibold text-secondary-dark small text-truncate" style="color: #1e293b !important; font-size: 0.85rem;">
                          {{ inc }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 2 COLUMNS: Islands to Visit & Tour Inclusions (Spacious & Visible) -->
          <div class="row g-4">
            <!-- Left: Islands to Visit -->
            <div class="col-md-6">
              <div class="p-4 rounded-4 bg-white border h-100 shadow-xs d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2.5 border-bottom">
                  <span class="d-flex align-items-center gap-2">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width: 30px; height: 30px; background: #bc9151; font-size: 0.85rem;">
                      <i class="bi bi-compass"></i>
                    </div>
                    <span class="fw-bold small text-uppercase tracking-wider text-secondary-dark" style="color: #0f172a !important;">Islands to Visit</span>
                  </span>
                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill x-small px-2.5 py-1 fw-bold">
                    1-Day Island Hopping
                  </span>
                </div>

                <div class="d-flex flex-column gap-2 flex-grow-1">
                  <div 
                    v-for="(dest, idx) in selectedGuestPackage.tour_destinations" 
                    :key="idx" 
                    class="d-flex align-items-center gap-2 p-2 rounded-3 border bg-light"
                    style="border-color: #e2e8f0 !important;"
                  >
                    <span class="fs-6">🏝️</span>
                    <span class="fw-bold text-secondary-dark small" style="color: #1e293b !important;">{{ dest }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right: Tour Inclusions -->
            <div class="col-md-6">
              <div class="p-4 rounded-4 bg-white border h-100 shadow-xs d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                  <span class="d-flex align-items-center gap-2">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white" style="width: 30px; height: 30px; background: #bc9151; font-size: 0.85rem;">
                      <i class="bi bi-card-checklist"></i>
                    </div>
                    <span class="fw-bold small text-uppercase tracking-wider text-secondary-dark" style="color: #0f172a !important;">Tour Inclusions</span>
                  </span>
                  <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill x-small px-2 py-1 fw-bold">
                    All-Inclusive Tour
                  </span>
                </div>

                <div class="d-flex flex-column gap-2 flex-grow-1">
                  <div 
                    v-for="(inc, idx) in selectedGuestPackage.tour_inclusions" 
                    :key="idx" 
                    class="d-flex align-items-start gap-2 py-1"
                  >
                    <i class="bi bi-check-circle-fill flex-shrink-0" style="color: #bc9151 !important; font-size: 0.95rem; margin-top: 2px;"></i>
                    <span class="fw-semibold text-secondary-dark small" style="color: #334155 !important; line-height: 1.45;">{{ inc }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Location & Phone (Generous padding, high contrast) -->
          <div class="p-3.5 p-md-4 rounded-4 bg-white border d-flex flex-wrap justify-content-between align-items-center gap-3 shadow-xs">
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-gold shadow-xs" style="width: 38px; height: 38px; color: #bc9151 !important;">
                <i class="bi bi-geo-alt-fill fs-5"></i>
              </div>
              <div>
                <small class="text-muted d-block xx-small text-uppercase fw-bold mb-0.5">Apartelle Location</small>
                <span class="fw-bold text-secondary-dark small" style="color: #0f172a !important;">{{ selectedGuestPackage.location || 'Pob. 1 General Luna (Along the road)' }}</span>
              </div>
            </div>
            <div class="d-flex align-items-center gap-3">
              <div class="rounded-circle bg-light d-flex align-items-center justify-content-center text-gold shadow-xs" style="width: 38px; height: 38px; color: #bc9151 !important;">
                <i class="bi bi-telephone-fill fs-5"></i>
              </div>
              <div>
                <small class="text-muted d-block xx-small text-uppercase fw-bold mb-0.5">Direct Inquiries &amp; Booking</small>
                <a :href="'tel:' + (selectedGuestPackage.contact_number || '09505602175')" class="fw-bold text-gold text-decoration-none small fs-6" style="color: #bc9151 !important;">
                  {{ selectedGuestPackage.contact_number || '0950 560 2175' }}
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Clean, Spacious Footer -->
        <div class="p-3 p-md-4 border-top bg-white d-flex justify-content-between align-items-center flex-wrap gap-3 flex-shrink-0">
          <button class="btn btn-light rounded-pill px-4 py-2 fw-bold text-muted border-0" @click="closeGuestPackageModal">
            Close
          </button>
          <div class="d-flex gap-3 align-items-center flex-wrap">
            <a 
              :href="'tel:' + (selectedGuestPackage.contact_number || '09505602175')" 
              class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold small d-inline-flex align-items-center gap-2"
            >
              <i class="bi bi-telephone-fill text-gold"></i> Inquire
            </a>
            <button 
              type="button" 
              class="btn btn-gold rounded-pill px-4 py-2 fw-bold shadow-gold d-inline-flex align-items-center gap-2 fs-6"
              style="background: linear-gradient(135deg, #bc9151 0%, #a2783d 100%) !important; color: #ffffff !important;"
              @click="bookPackageOnline(selectedGuestPackage)"
            >
              <i class="bi bi-shield-lock-fill"></i>
              Book &amp; Pay Online (₱{{ formatPrice(selectedGuestPackage.price) }})
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ========== DESIGNER LABEL ========== */
.designer-label {
  display: flex;
  align-items: center;
  gap: 1rem;
  width: fit-content;
}

.label-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  display: inline-block;
}

.label-text {
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 5px;
  text-transform: uppercase;
}

/* Page Header */
.page-header {
  background: url('/images/unsplash/room-interior.jpg') center/cover no-repeat;
  position: relative;
  padding: 12rem 0 10rem; /* Increased for navbar offset + breathing room */
  background-attachment: fixed;
}

.contrast-overlay::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(rgba(26, 38, 52, 0.8), rgba(26, 38, 52, 0.95));
}

/* Rooms Content */
.rooms-content .container {
  max-width: 1440px;
}

.py-7 {
  padding-top: 4.5rem;
  padding-bottom: 6rem;
}

.mb-7 {
  margin-bottom: 3.5rem;
}

/* Filter Tabs */
.filter-tabs {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: nowrap;
  overflow-x: auto;
  padding: 0.5rem 0.25rem 1rem;
  scrollbar-width: none;
  -ms-overflow-style: none;
  -webkit-overflow-scrolling: touch;
}

.filter-tabs::-webkit-scrollbar {
  display: none;
}

.filter-tab {
  padding: 0.75rem 1.4rem;
  border: 1px solid rgba(0, 0, 0, 0.06);
  background: white;
  color: #64748b;
  font-weight: 700;
  border-radius: 50px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 1px;
  white-space: nowrap;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.filter-tab:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(188, 145, 81, 0.15);
  color: var(--primary-gold);
  border-color: rgba(188, 145, 81, 0.3);
}

.filter-tab.active {
  background: white;
  border-color: var(--primary-gold);
  color: var(--primary-gold);
  box-shadow: 0 8px 24px rgba(188, 145, 81, 0.22);
}

/* Special Filter Tabs: Promo Deals & Tour Packages */
.filter-tab.filter-tab-promo {
  background: #fff1f2;
  border: 1.5px solid #fecdd3;
  color: #e11d48;
  box-shadow: 0 4px 12px rgba(225, 29, 72, 0.08);
}

.filter-tab.filter-tab-promo i {
  color: #e11d48;
  transition: color 0.2s ease;
}

.filter-tab.filter-tab-promo:hover {
  background: #ffe4e6;
  border-color: #f43f5e;
  color: #be123c;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(225, 29, 72, 0.18);
}

.filter-tab.filter-tab-promo:hover i {
  color: #be123c;
}

.filter-tab.filter-tab-promo.active {
  background: linear-gradient(135deg, #f43f5e 0%, #e11d48 50%, #be123c 100%);
  border-color: #be123c;
  color: #ffffff !important;
  box-shadow: 0 8px 24px rgba(225, 29, 72, 0.35);
}

.filter-tab.filter-tab-promo.active i {
  color: #ffffff !important;
}

.filter-tab.filter-tab-packages {
  background: #f0fdfa;
  border: 1.5px solid #99f6e4;
  color: #0f766e;
  box-shadow: 0 4px 12px rgba(13, 148, 136, 0.08);
}

.filter-tab.filter-tab-packages i {
  color: #0d9488;
  transition: color 0.2s ease;
}

.filter-tab.filter-tab-packages:hover {
  background: #ccfbf1;
  border-color: #2dd4bf;
  color: #115e59;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(13, 148, 136, 0.18);
}

.filter-tab.filter-tab-packages:hover i {
  color: #115e59;
}

.filter-tab.filter-tab-packages.active {
  background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);
  border-color: #0f766e;
  color: #ffffff !important;
  box-shadow: 0 8px 24px rgba(13, 148, 136, 0.35);
}

.filter-tab.filter-tab-packages.active i {
  color: #ffffff !important;
}

/* Room Card Premium */
.room-card {
  background: white;
  border-radius: 24px;
  overflow: hidden;
  transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
  height: 100%;
}

.shadow-gold-sm {
    box-shadow: 0 15px 40px rgba(188, 145, 81, 0.06);
}

.room-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 45px rgba(188, 145, 81, 0.15);
}

.room-card-image {
  position: relative;
  height: 200px;
  overflow: hidden;
}

.room-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(15, 23, 42, 0.4) 100%);
}

.room-card-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 1.5s cubic-bezier(0.16, 1, 0.3, 1);
}

.room-card:hover .room-card-image img {
  transform: scale(1.1);
}

.room-badge {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  padding: 0.3rem 0.65rem;
  border-radius: 50px;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  z-index: 2;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
}

.promo-badge {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  padding: 0.3rem 0.65rem;
  border-radius: 50px;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.4px;
  z-index: 2;
  box-shadow: 0 4px 10px rgba(220, 38, 38, 0.25);
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
}

.room-size-tag {
  position: absolute;
  bottom: 0.75rem;
  right: 0.75rem;
  background: rgba(255,255,255,0.95);
  padding: 0.3rem 0.65rem;
  border-radius: 50px;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--secondary-dark);
  backdrop-filter: blur(5px);
  z-index: 2;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
}

.badge-available { 
  background: #10b981 !important; 
  color: #ffffff !important; 
}
.badge-unavailable { 
  background: #ef4444 !important; 
  color: #ffffff !important; 
}
.badge-maintenance { 
  background: #f59e0b !important; 
  color: #ffffff !important; 
}

.room-card-body {
  padding: 1.25rem 1.15rem 1.25rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.room-type-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 800;
    margin-bottom: 0.35rem;
}

.room-card-title {
  font-weight: 700;
  font-size: 1.35rem;
  margin-bottom: 0.4rem;
  color: var(--secondary-dark);
}

.room-card-price {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
    min-height: 40px;
}

.room-card-price .currency {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text-dark);
}

.room-card-price .price {
  font-family: var(--font-serif);
  font-weight: 700;
  font-size: 1.55rem;
  color: var(--primary-gold);
}

.room-card-price .period {
  font-size: 0.78rem;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.room-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    min-height: 58px;
    align-content: flex-start;
}

.feature-pill {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    padding: 0.35rem 0.65rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 700;
}

.feature-pill i {
    color: var(--primary-gold);
    font-size: 0.95rem;
}

.room-card-description {
  font-size: 0.8rem;
  color: #64748b;
  line-height: 1.5;
  margin-bottom: 0.75rem !important;
}

.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.btn-gold-outline {
    background: transparent;
    border: 2px solid var(--primary-gold);
    color: var(--primary-gold);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-gold-outline:hover {
    background: var(--primary-gold);
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(188, 145, 81, 0.3);
}

.glass-panel {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.op-50 { opacity: 0.5; }
.pointer-none { pointer-events: none; }

@media (max-width: 991.98px) {
  .page-header { padding: 10rem 0 6rem; }
  .room-card-body { padding: 2rem; }
  .room-card-title { font-size: 1.75rem; }
  .filter-tabs {
    justify-content: flex-start;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
  }
}

@media (max-width: 767.98px) {
  .py-7 { padding-top: 3.5rem; padding-bottom: 4rem; }
  .mb-7 { margin-bottom: 2rem; }
  .filter-tab { padding: 0.65rem 1.1rem; font-size: 0.7rem; letter-spacing: 0.5px; }
}

/* ========== SKELETON LOADING ========== */
.skeleton-card {
  background: white;
  border-radius: 32px;
  overflow: hidden;
}

.skeleton-image {
  height: 220px;
  background: #e2e8f0;
}

.skeleton-line {
  background: #e2e8f0;
  border-radius: 8px;
}

.skeleton-pill {
  width: 90px;
  height: 30px;
  background: #e2e8f0;
  border-radius: 14px;
}

.skeleton-btn {
  width: 100%;
  height: 42px;
  background: #e2e8f0;
  border-radius: 50px;
}

.skeleton-shimmer {
  position: relative;
  overflow: hidden;
}

.skeleton-shimmer::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255, 255, 255, 0.5) 50%,
    transparent 100%
  );
  animation: shimmer-sweep 1.5s ease-in-out infinite;
}

@keyframes shimmer-sweep {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

/* Premium Package Modal Enhancements */
.bg-cream-soft {
  background-color: #fbfaf8 !important;
}

.amenity-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.35rem 0.75rem;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 50px;
  font-size: 0.75rem;
  font-weight: 500;
  color: #1f2937;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  transition: all 0.2s ease;
}

.amenity-chip:hover {
  border-color: var(--primary-gold);
  transform: translateY(-1px);
}

.destination-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.55rem 0.85rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.82rem;
  color: #1f2937;
  transition: all 0.2s ease;
}

.destination-item:hover {
  background: #ffffff;
  border-color: #38bdf8;
  transform: translateX(3px);
}

.inclusion-item {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.45rem 0.65rem;
  border-radius: 10px;
  font-size: 0.82rem;
  color: #374151;
  line-height: 1.45;
  transition: all 0.15s ease;
}

.inclusion-item:hover {
  background: #fdfbf7;
}

.shadow-xs {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
</style>
