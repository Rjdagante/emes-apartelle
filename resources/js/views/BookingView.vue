<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useAuth } from '../store/auth';
import CalendarPicker from '../components/CalendarPicker.vue';
import GuestSelector from '../components/GuestSelector.vue';
import QrPaymentModal from '../components/QrPaymentModal.vue';

const router = useRouter();

const route = useRoute();
const { state } = useAuth();
const isSuspended = computed(() => state.user?.is_suspended);
// Steps: 1 = Select Dates/Room, 2 = Guest Details, 3 = Payment
const currentStep = ref(1);

// Parse guests from query string "1 Guest" -> 1
const parseGuests = (guestsStr) => {
    if (!guestsStr) return 1;
    const match = guestsStr.match(/(\d+)/);
    return match ? parseInt(match[1]) : 1;
};

// Form Data - Initialize with auth state if available
const booking = ref({
    checkIn: route.query.checkIn || '',
    checkOut: route.query.checkOut || '',
    roomId: route.query.roomId ? parseInt(route.query.roomId) : '',
    guests: parseGuests(route.query.guests),
    adults: route.query.adults ? Number(route.query.adults) : parseGuests(route.query.guests),
    children: route.query.children ? Number(route.query.children) : 0,
    childrenAges: route.query.childrenAges 
        ? (typeof route.query.childrenAges === 'string' ? JSON.parse(route.query.childrenAges) : route.query.childrenAges)
        : [],
    extraBeds: 0,
    firstName: state.user?.first_name || '',
    lastName: state.user?.last_name || '',
    email: state.user?.email || '',
    phone: state.user?.phone || '',
    address: state.user?.address || '',
    paymentOption: 'full', // Default to full payment
});

const freeChildrenCount = computed(() => {
    if (!booking.value.childrenAges || !Array.isArray(booking.value.childrenAges)) return 0;
    return booking.value.childrenAges.filter(age => Number(age) < 7).length;
});

const payingChildrenCount = computed(() => {
    if (!booking.value.childrenAges || !Array.isArray(booking.value.childrenAges)) return 0;
    return booking.value.childrenAges.filter(age => Number(age) >= 7).length;
});

const chargeableGuests = computed(() => {
    return Number(booking.value.adults || 1) + payingChildrenCount.value;
});

const totalGuests = computed(() => {
    return Number(booking.value.adults || 1) + Number(booking.value.children || 0);
});

const rooms = ref([]);
const loading = ref(true);
const bookedDates = ref([]);
const showQrPaymentModal = ref(false);
const publicSettings = ref({ gcash_number: '', gcash_account_name: '', gcash_qr_url: null });

const fetchPublicSettings = async () => {
    try {
        const res = await axios.get('/api/settings/public');
        publicSettings.value = res.data || {};
    } catch (err) {
        console.error('Error fetching public settings', err);
    }
};

const fetchBookedDates = async () => {
    try {
        let url = '/api/rooms/booked-dates';
        if (booking.value.roomId) {
            url = `/api/rooms/${booking.value.roomId}/booked-dates`;
        }
        
        const response = await axios.get(url);
        bookedDates.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
        console.error("Error fetching booked dates", err);
        bookedDates.value = [];
    }
};

const formatDateRange = (checkIn, checkOut) => {
    if (!checkIn || !checkOut) return '';
    const start = new Date(String(checkIn).replace(' ', 'T'));
    const end = new Date(String(checkOut).replace(' ', 'T'));
    const opts = { month: 'short', day: 'numeric', year: 'numeric' };
    return `${start.toLocaleDateString('en-US', opts)} - ${end.toLocaleDateString('en-US', opts)}`;
};

const fetchRooms = async () => {
    loading.value = true;
    try {
        const params = {};
        if (booking.value.checkIn && booking.value.checkOut) {
            params.check_in = booking.value.checkIn;
            params.check_out = booking.value.checkOut;
        }
        
        const response = await axios.get('/api/rooms', { params });
        rooms.value = response.data.filter(r => r.status === 'available');
        
        const availablePool = selectableRooms.value;

        // If selected room is no longer available for new dates (or outside assigned package room type), reset it
        if (booking.value.roomId && !availablePool.find(r => r.id === parseInt(booking.value.roomId))) {
            booking.value.roomId = '';
        }

        // Auto-select first available room if none selected and dates are picked
        if (!booking.value.roomId && availablePool.length > 0 && booking.value.checkIn) {
            booking.value.roomId = availablePool[0].id;
        }
    } catch (err) {
        console.error("Error fetching rooms", err);
        rooms.value = [];
    } finally {
        loading.value = false;
    }
};

// Package Booking State
const packageId = ref(route.query.packageId ? parseInt(route.query.packageId) : null);
const selectedPackage = ref(null);
const packageLoading = ref(false);

const assignedPackageRoomType = computed(() => {
    if (!selectedPackage.value) return null;
    if (selectedPackage.value.room?.room_type) {
        return selectedPackage.value.room.room_type;
    }
    if (selectedPackage.value.room_id) {
        const linkedRoom = rooms.value.find(r => r.id === parseInt(selectedPackage.value.room_id));
        if (linkedRoom?.room_type) return linkedRoom.room_type;
    }
    return selectedPackage.value.room_type_label || null;
});

const selectableRooms = computed(() => {
    if (!selectedPackage.value) return rooms.value;

    const assignedType = assignedPackageRoomType.value;
    if (assignedType) {
        const normalized = assignedType.trim().toLowerCase();
        const matched = rooms.value.filter(r => {
            const rt = (r.room_type || '').trim().toLowerCase();
            return rt === normalized || rt.includes(normalized) || normalized.includes(rt);
        });
        if (matched.length > 0) return matched;
    }

    if (selectedPackage.value.room_id) {
        const exact = rooms.value.filter(r => r.id === parseInt(selectedPackage.value.room_id));
        if (exact.length > 0) return exact;
    }

    return rooms.value;
});

const packageNights = computed(() => {
    if (!selectedPackage.value) return 2;
    const dur = (selectedPackage.value.duration || '').toLowerCase();
    const match = dur.match(/(\d+)\s*(?:night|n\b)/);
    if (match) return parseInt(match[1]);
    const dayMatch = dur.match(/(\d+)\s*(?:day|d\b)/);
    if (dayMatch) return Math.max(1, parseInt(dayMatch[1]) - 1);
    return 2;
});

const fetchPackage = async () => {
    if (!packageId.value) return;
    packageLoading.value = true;
    try {
        const res = await axios.get(`/api/room-packages/public/${packageId.value}`);
        selectedPackage.value = res.data;
        if (selectedPackage.value) {
            if (selectedPackage.value.room_id) {
                booking.value.roomId = selectedPackage.value.room_id;
            }
            const paxMatch = (selectedPackage.value.pax || '').match(/(\d+)/);
            if (paxMatch) {
                booking.value.guests = parseInt(paxMatch[1]);
            }
            if (booking.value.checkIn) {
                const d = new Date(booking.value.checkIn);
                d.setDate(d.getDate() + packageNights.value);
                booking.value.checkOut = formatToYMD(d);
            }
        }
    } catch (err) {
        console.error('Error fetching package for booking', err);
    } finally {
        packageLoading.value = false;
    }
};

onMounted(() => {
    fetchRooms();
    fetchBookedDates();
    fetchPackage();
    fetchPublicSettings();
});

// Watch for date changes to update available rooms
watch(() => [booking.value.checkIn, booking.value.checkOut], () => {
    if (booking.value.checkIn && booking.value.checkOut) {
        fetchRooms();
    }
});

const selectedRoom = computed(() => {
    return rooms.value.find(r => r.id === parseInt(booking.value.roomId));
});

// Watch for selectedRoom changes to auto-adjust guests based on capacity and min occupancy
watch(selectedRoom, (newRoom) => {
    if (newRoom) {
        const maxAllowed = Number(newRoom.max_occupancy || 1);
        if (booking.value.adults > maxAllowed) {
            booking.value.adults = maxAllowed;
            booking.value.children = 0;
            booking.value.childrenAges = [];
        } else if (booking.value.adults + booking.value.children > maxAllowed) {
            booking.value.children = Math.max(0, maxAllowed - booking.value.adults);
            booking.value.childrenAges = (booking.value.childrenAges || []).slice(0, booking.value.children);
        }
        booking.value.guests = booking.value.adults + booking.value.children;
        
        // Auto adjust booking guests to min_occupancy if it is less than the minimum
        if (newRoom.min_occupancy && booking.value.guests < newRoom.min_occupancy) {
            booking.value.guests = newRoom.min_occupancy;
            booking.value.adults = Math.max(booking.value.adults, newRoom.min_occupancy);
        }
        
        // Special case for Single rooms to be exactly 1 guest
        if (newRoom.room_type.toLowerCase().includes('single')) {
            booking.value.guests = 1;
            booking.value.adults = 1;
            booking.value.children = 0;
            booking.value.childrenAges = [];
        }

        // Clamp extra beds to the newly selected room's max_extra_guests
        const maxExtra = Number(newRoom.max_extra_guests ?? 2);
        if (booking.value.extraBeds > maxExtra) {
            booking.value.extraBeds = maxExtra;
        }
    } else {
        booking.value.extraBeds = 0;
    }
});

// Watch for guest changes to ensure they stay within selected room's capacity and minimum occupancy constraints
watch(() => booking.value.guests, (newGuests) => {
    if (selectedRoom.value) {
        const maxAllowed = Number(selectedRoom.value.max_occupancy || 1);
        if (newGuests > maxAllowed) {
            booking.value.guests = maxAllowed;
        }
        if (selectedRoom.value.min_occupancy && newGuests < selectedRoom.value.min_occupancy) {
            booking.value.guests = selectedRoom.value.min_occupancy;
        }
    }
});

// Watch for room change to update booked dates
watch(() => booking.value.roomId, (newId) => {
    fetchBookedDates();
    showAllAmenities.value = false; // Reset toggle on room change
});

const showAllAmenities = ref(false);

const displayedAmenities = computed(() => {
    if (!selectedRoom.value?.amenities) return [];
    if (showAllAmenities.value) {
        return selectedRoom.value.amenities;
    }
    return selectedRoom.value.amenities.slice(0, 6);
});

const showRoomModal = ref(false);

const openRoomModal = () => {
    if (selectedRoom.value) {
        showRoomModal.value = true;
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

const totalNights = computed(() => {
    if (!booking.value.checkIn || !booking.value.checkOut) return 0;
    const start = new Date(booking.value.checkIn);
    const end = new Date(booking.value.checkOut);
    const diff = end - start;
    const nights = Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
    return nights > 0 ? nights : 0;
});

const maxCheckOutDate = computed(() => {
    if (!booking.value.checkIn || !bookedDates.value.length) return null;
    const checkInDate = new Date(booking.value.checkIn);
    checkInDate.setHours(0, 0, 0, 0);

    let firstNextDate = null;
    bookedDates.value.forEach(range => {
        const start = new Date(range.check_in || range.start);
        start.setHours(0, 0, 0, 0);
        if (start >= checkInDate) {
            if (!firstNextDate || start < new Date(firstNextDate)) {
                firstNextDate = range.check_in || range.start;
            }
        }
    });

    return firstNextDate ? firstNextDate.split(' ')[0] : null;
});

watch(() => booking.value.checkIn, (newCheckIn) => {
    if (selectedPackage.value && newCheckIn) {
        const d = new Date(newCheckIn);
        d.setDate(d.getDate() + packageNights.value);
        booking.value.checkOut = formatToYMD(d);
        return;
    }
    if (booking.value.checkOut) {
        if (newCheckIn && booking.value.checkOut <= newCheckIn) {
            booking.value.checkOut = '';
        } else if (maxCheckOutDate.value && booking.value.checkOut > maxCheckOutDate.value) {
            booking.value.checkOut = '';
        }
    }
});

const maxExtraBeds = computed(() => {
    if (!selectedRoom.value || selectedPackage.value) return 0;
    return Number(selectedRoom.value.max_extra_guests ?? 2);
});

const extraGuestsCount = computed(() => {
    if (!selectedRoom.value || selectedPackage.value) return 0;
    return Number(booking.value.extraBeds || 0);
});

const extraBedPrice = computed(() => {
    if (!selectedRoom.value) return 300;
    return Number(selectedRoom.value.extra_bed_price ?? 300);
});

const incrementExtraBeds = () => {
    if (booking.value.extraBeds < maxExtraBeds.value) {
        booking.value.extraBeds++;
    }
};

const decrementExtraBeds = () => {
    if (booking.value.extraBeds > 0) {
        booking.value.extraBeds--;
    }
};

const effectiveRate = computed(() => {
    if (!selectedRoom.value) return 0;
    const isPerHead = selectedRoom.value.room_type === 'Family Room' || selectedRoom.value.room_type === 'Barkadahan Room';
    if (selectedRoom.value.is_promo) {
        if (isPerHead && selectedRoom.value.promo_price_per_head !== null && Number(selectedRoom.value.promo_price_per_head) > 0) {
            return Number(selectedRoom.value.promo_price_per_head);
        }
        if (!isPerHead && selectedRoom.value.promo_price !== null && Number(selectedRoom.value.promo_price) > 0) {
            return Number(selectedRoom.value.promo_price);
        }
    }
    return isPerHead ? Number(selectedRoom.value.price_per_head || 0) : Number(selectedRoom.value.price_per_night || 0);
});

const originalRate = computed(() => {
    if (!selectedRoom.value) return 0;
    const isPerHead = selectedRoom.value.room_type === 'Family Room' || selectedRoom.value.room_type === 'Barkadahan Room';
    return isPerHead ? Number(selectedRoom.value.price_per_head || 0) : Number(selectedRoom.value.price_per_night || 0);
});

const extraBeddingTotal = computed(() => {
    if (totalNights.value <= 0) return 0;
    return extraGuestsCount.value * extraBedPrice.value * totalNights.value;
});

const basePriceTotal = computed(() => {
    if (!selectedRoom.value || totalNights.value <= 0) return 0;
    if (selectedRoom.value.room_type === 'Family Room' || selectedRoom.value.room_type === 'Barkadahan Room') {
        return effectiveRate.value * chargeableGuests.value * totalNights.value;
    }
    return effectiveRate.value * totalNights.value;
});

const totalPrice = computed(() => {
    if (selectedPackage.value) {
        return Number(selectedPackage.value.price || 0);
    }
    return basePriceTotal.value + extraBeddingTotal.value;
});

const amountDue = computed(() => {
    return booking.value.paymentOption === 'half' ? totalPrice.value / 2 : totalPrice.value;
});

const maxSelectableGuests = computed(() => {
    if (!selectedRoom.value) return 4;
    return Number(selectedRoom.value.max_occupancy || 4);
});

const validateStep = (step) => {
    if (step === 1) {
        if (!booking.value.checkIn) return "Please select a Check-in date.";
        if (!booking.value.checkOut) return "Please select a Check-out date.";
        if (totalNights.value <= 0) return "Invalid date range selected.";
        if (!booking.value.roomId) return "Please select a Room.";
        
        // Check for booked dates overlap
        if (bookedDates.value.length > 0) {
            const start = new Date(booking.value.checkIn);
            const end = new Date(booking.value.checkOut);
            start.setHours(0, 0, 0, 0);
            end.setHours(0, 0, 0, 0);

            const isOverlapping = bookedDates.value.some(range => {
                const bStart = new Date(range.check_in);
                const bEnd = new Date(range.check_out);
                bStart.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return start < bEnd && end > bStart;
            });

            if (isOverlapping) {
                return "The selected room is already booked for one or more dates in your range.";
            }
        }
        
        // Check max occupancy
        if (selectedRoom.value) {
            const maxAllowed = Number(selectedRoom.value.max_occupancy || 1);
            if (booking.value.guests > maxAllowed) {
                return `Maximum guests for ${selectedRoom.value.room_type} is ${maxAllowed}.`;
            }
        }
    } else if (step === 2) {
        if (!booking.value.firstName) return "First Name is required.";
        if (!booking.value.lastName) return "Last Name is required.";
        if (!booking.value.email) return "Email Address is required.";
        if (!booking.value.phone) return "Phone Number is required.";
        if (!/^\S+@\S+\.\S+$/.test(booking.value.email)) return "Please enter a valid email address.";
    }
    return null;
};

const nextStep = () => {
    const error = validateStep(currentStep.value);
    if (error) {
        Swal.fire({
            icon: 'warning',
            title: 'Incomplete Details',
            text: error,
            confirmButtonColor: '#BC9151'
        });
        return;
    }
    if (currentStep.value < 3) currentStep.value++;
};

const prevStep = () => {
    if (currentStep.value > 1) currentStep.value--;
};

const formatPrice = (price) => {
    return parseFloat(price || 0).toLocaleString();
};

const handleBooking = async () => {
    if (isSuspended.value) {
        Swal.fire({
            icon: 'error',
            title: 'Account Suspended',
            text: 'Your account is suspended and cannot perform bookings. Please contact support.',
            confirmButtonColor: '#dc3545'
        });
        return;
    }

    if (!state.isAuthenticated) {
        Swal.fire({
            icon: 'info',
            title: 'Sign In Required',
            text: 'You need to be logged in to make a reservation.',
            showCancelButton: true,
            confirmButtonText: 'Sign In',
            confirmButtonColor: '#BC9151'
        }).then((result) => {
            if (result.isConfirmed) {
                router.push('/login?redirect=/booking');
            }
        });
        return;
    }
    
    showQrPaymentModal.value = true;
};

const submitBookingWithProof = async (screenshotFile) => {
    loading.value = true;
    try {
        const formData = new FormData();
        formData.append('user_id', state.user.id);
        formData.append('room_id', booking.value.roomId);
        if (selectedPackage.value) {
            formData.append('package_id', selectedPackage.value.id);
        }
        formData.append('check_in', booking.value.checkIn.includes('T') ? booking.value.checkIn : `${booking.value.checkIn}T13:00:00`);
        formData.append('check_out', booking.value.checkOut.includes('T') ? booking.value.checkOut : `${booking.value.checkOut}T11:00:00`);
        formData.append('guests', totalGuests.value);
        formData.append('adults', booking.value.adults);
        formData.append('children', booking.value.children);
        formData.append('children_ages', JSON.stringify(booking.value.childrenAges || []));
        formData.append('extra_beds', selectedPackage.value ? 0 : Number(booking.value.extraBeds || 0));
        formData.append('guest_name', `${booking.value.firstName} ${booking.value.lastName}`.trim());
        formData.append('payment_option', booking.value.paymentOption);
        formData.append('payment_screenshot', screenshotFile);

        const response = await axios.post('/api/reservations', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        showQrPaymentModal.value = false;
        const reservationId = response.data?.reservation?.id;
        if (reservationId) {
            router.push({ path: '/booking/success', query: { res_id: reservationId } });
        } else {
            router.push('/booking/success');
        }
    } catch (err) {
        console.error("Booking failed", err);
        const firstError = err.response?.data?.payment_screenshot?.[0];
        Swal.fire({
            icon: 'error',
            title: 'Booking Failed',
            text: firstError || err.response?.data?.message || 'An error occurred while creating your reservation.'
        });
    } finally {
        loading.value = false;
    }
};

// Calendar logic
const days = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

const formatToYMD = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
</script>

<template>
    <div class="booking-page bg-cream min-vh-100">
        <!-- Hero Header -->
        <section class="booking-hero py-5">
            <div class="container text-center animate-fade-up">
                <span class="section-label-alt mb-2 d-block">MAKE A RESERVATION</span>
                <h1 class="display-3 fw-bold serif-font mb-3 text-secondary-dark">Book Your Stay</h1>
                <p class="section-description mx-auto text-muted" style="max-width: 500px;">
                    Select your dates and room, then complete your booking in just a few steps.
                </p>
            </div>
        </section>

        <!-- Progress Steps -->
        <div class="container mb-5">
            <div class="booking-steps justify-content-center animate-fade-up delay-1">
                <div class="step-item" :class="{ 'active': currentStep >= 1 }">
                    <div class="step-icon">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <span class="step-label">Select Dates</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" :class="{ 'active': currentStep >= 2 }">
                    <div class="step-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <span class="step-label">Guest Details</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item" :class="{ 'active': currentStep >= 3 }">
                    <div class="step-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <span class="step-label">Review &amp; Receipt</span>
                </div>
            </div>
        </div>

        <div class="container pb-5">
            <div class="row g-4 g-lg-5">
                <!-- Main Form Area -->
                <div class="col-lg-8">
                    <!-- Step 1: Select Room and Dates -->
                    <div v-if="currentStep === 1" class="booking-section animate-fade-up delay-2">
                        <!-- Special Package Deal Banner -->
                        <div v-if="selectedPackage" class="card border-0 rounded-4 p-4 mb-4 shadow-sm text-white position-relative overflow-hidden animate-fade-in" style="background: linear-gradient(135deg, #1b382b 0%, #2a5240 100%);">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 position-relative z-1">
                                <div>
                                    <span class="badge bg-gold text-white px-3 py-1 rounded-pill small fw-bold text-uppercase mb-2">
                                        🌴 Selected Tour Package 🌴
                                    </span>
                                    <h3 class="serif-font fw-bold mb-1 text-white">{{ selectedPackage.name }}</h3>
                                    <p class="text-white-50 small mb-3" v-if="selectedPackage.tagline">{{ selectedPackage.tagline }}</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge bg-white bg-opacity-25 text-white px-3 py-1.5 rounded-pill small">
                                            <i class="bi bi-clock-history me-1 text-gold"></i>{{ selectedPackage.duration || '3D, 2N' }}
                                        </span>
                                        <span class="badge bg-white bg-opacity-25 text-white px-3 py-1.5 rounded-pill small">
                                            <i class="bi bi-people-fill me-1 text-gold"></i>{{ selectedPackage.pax || 'Good for 2 pax' }}
                                        </span>
                                        <span class="badge bg-white bg-opacity-25 text-white px-3 py-1.5 rounded-pill small">
                                            <i class="bi bi-compass me-1 text-gold"></i>{{ selectedPackage.tour_type || 'Joiners Tour' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-md-end">
                                    <span class="d-block text-white-50 xx-small text-uppercase fw-bold">Package Rate</span>
                                    <span class="h2 fw-bold text-gold serif-font mb-0">₱{{ formatPrice(selectedPackage.price) }}</span>
                                    <small class="text-white-50 d-block x-small">All-Inclusive Package Deal</small>
                                </div>
                            </div>

                            <!-- Highlights preview -->
                            <div class="mt-3 pt-3 border-top border-white border-opacity-10 d-flex flex-wrap gap-2">
                                <template v-if="selectedPackage.room?.amenities && selectedPackage.room.amenities.length > 0">
                                    <span v-for="amenity in selectedPackage.room.amenities.slice(0, 3)" :key="'sra-'+amenity.id" class="badge bg-white bg-opacity-10 text-white rounded-pill x-small px-2.5 py-1">
                                        <i class="bi bi-check2 text-gold me-1"></i>{{ amenity.name }}
                                    </span>
                                </template>
                                <template v-else-if="selectedPackage.room_inclusions && selectedPackage.room_inclusions.length > 0">
                                    <span v-for="(inc, idx) in selectedPackage.room_inclusions.slice(0, 3)" :key="'sri-'+idx" class="badge bg-white bg-opacity-10 text-white rounded-pill x-small px-2.5 py-1">
                                        <i class="bi bi-check2 text-gold me-1"></i>{{ inc }}
                                    </span>
                                </template>
                                <span v-for="(dest, idx) in (selectedPackage.tour_destinations || []).slice(0, 3)" :key="'std-'+idx" class="badge bg-white bg-opacity-10 text-white rounded-pill x-small px-2.5 py-1">
                                    🏝️ {{ dest }}
                                </span>
                                <span v-if="(selectedPackage.tour_inclusions || []).length > 0" class="badge bg-gold text-white rounded-pill x-small px-2.5 py-1">
                                    🍱 Boodle Fight Lunch Included
                                </span>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 booking-main-card">
                            <h2 class="serif-font h3 mb-4 text-secondary-dark">Select Room</h2>
                            <div class="row g-4 mb-5">
                                <div class="col-12">
                                    <label class="form-label-custom">
                                        Room Type
                                        <span v-if="selectedPackage && assignedPackageRoomType" class="text-gold fw-normal ms-1">
                                            ({{ assignedPackageRoomType }} only)
                                        </span>
                                    </label>
                                    <div class="input-group">
                                        <select class="form-select form-control-custom py-3" v-model="booking.roomId" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                            <option value="" disabled>Select a room</option>
                                            <option v-for="room in selectableRooms" :key="room.id" :value="room.id">
                                                {{ room.room_type }} #{{ room.room_number }} - 
                                                <template v-if="room.is_promo">
                                                    [PROMO] ₱{{ formatPrice((room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? (room.promo_price_per_head || room.price_per_head) : (room.promo_price || room.price_per_night)) }}{{ (room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room') ? '/head/night' : '/night' }}
                                                </template>
                                                <template v-else-if="room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room'">
                                                    ₱{{ formatPrice(room.price_per_head) }}/head/night
                                                </template>
                                                <template v-else>
                                                    ₱{{ formatPrice(room.price_per_night) }}/night
                                                </template>
                                            </option>
                                        </select>
                                        <button 
                                            v-if="booking.roomId"
                                            class="btn btn-outline-secondary px-3" 
                                            type="button"
                                            @click="booking.roomId = ''"
                                            title="Clear Selection"
                                            style="border-color: #eee; border-left: 0;"
                                        >
                                            <i class="bi bi-x-lg text-danger"></i> <span class="d-none d-sm-inline ms-1 small text-danger">Clear</span>
                                        </button>
                                        <button 
                                            class="btn btn-outline-secondary px-3" 
                                            type="button"
                                            :disabled="!booking.roomId"
                                            @click="openRoomModal"
                                            title="View Room Details"
                                            style="border-color: #eee; border-left: 0;"
                                        >
                                            <i class="bi bi-eye text-primary"></i> <span class="d-none d-sm-inline ms-1 small">View</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Selected Room Details & Amenities Preview -->
                                <div v-if="selectedRoom" class="col-12 mt-4 animate-fade-up">
                                    <div class="card border border-light-subtle rounded-4 p-4 shadow-sm" style="background-color: var(--bg-light);">
                                        <div class="row g-4 align-items-center">
                                            <div class="col-md-5">
                                                <div class="position-relative overflow-hidden rounded-3 shadow-sm" style="height: 180px;">
                                                    <img :src="getRoomImage(selectedRoom)" class="w-100 h-100 object-fit-cover animate-fade-in" :alt="selectedRoom.room_type" @error="onImageError($event, selectedRoom.room_type)">
                                                    <div class="position-absolute top-0 start-0 m-2">
                                                        <span class="badge bg-gold text-white px-3 py-2 rounded-pill shadow-sm">
                                                            Room #{{ selectedRoom.room_number }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-7">
                                                <span class="text-gold text-uppercase letter-spacing-wide small fw-bold mb-1 d-block">{{ selectedRoom.room_type }}</span>
                                                <h4 class="serif-font mb-2 text-secondary-dark">Room #{{ selectedRoom.room_number }}</h4>
                                                
                                                <div class="d-flex align-items-center gap-3 mb-3 text-muted small">
                                                     <span v-if="selectedRoom.room_size"><i class="bi bi-arrows-fullscreen text-gold me-1"></i> {{ selectedRoom.room_size }} m²</span>
                                                     <span v-if="selectedRoom.bed_type"><i class="bi bi-hdd-stack text-gold me-1"></i> {{ selectedRoom.bed_type }}</span>
                                                     <span><i class="bi bi-people text-gold me-1"></i> Max {{ selectedRoom.max_occupancy }} Guests</span>
                                                </div>
                                                
                                                <p class="text-muted small mb-3 line-clamp-2" style="font-size: 0.85rem; line-height: 1.5;">{{ selectedRoom.description }}</p>
                                                
                                                <div v-if="selectedRoom.amenities && selectedRoom.amenities.length">
                                                    <p class="small fw-bold text-uppercase text-muted mb-2" style="font-size: 0.75rem; letter-spacing: 0.5px;">Included Amenities</p>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <span v-for="amenity in displayedAmenities" :key="amenity.id" class="badge bg-white text-dark fw-normal py-2 px-3 rounded-pill border small">
                                                            <i :class="['bi', amenity.icon, 'text-gold me-1']"></i> {{ amenity.name }}
                                                        </span>
                                                    </div>
                                                    
                                                    <button 
                                                        v-if="selectedRoom.amenities.length > 6" 
                                                        @click="showAllAmenities = !showAllAmenities" 
                                                        class="btn btn-link text-gold p-0 mt-3 small fw-bold text-decoration-none d-inline-flex align-items-center gap-1 shadow-none"
                                                        style="font-size: 0.75rem; border: none; background: transparent;"
                                                    >
                                                        <template v-if="showAllAmenities">
                                                            <span>Hide amenities</span> <i class="bi bi-chevron-up"></i>
                                                        </template>
                                                        <template v-else>
                                                            <span>View all amenities (+{{ selectedRoom.amenities.length - 6 }})</span> <i class="bi bi-chevron-down"></i>
                                                        </template>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Room Details Modal -->
                                <Teleport to="body">
                                    <div v-if="showRoomModal" class="modal-backdrop fade show" style="z-index: 1050;"></div>
                                    <div v-if="showRoomModal" class="modal fade show d-block" tabindex="-1" style="z-index: 1055;" @click.self="showRoomModal = false">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
                                                <div class="modal-header border-0 position-absolute end-0 top-0 z-2 p-3">
                                                    <button type="button" class="btn-close bg-white opacity-75 shadow-sm rounded-circle p-2" @click="showRoomModal = false"></button>
                                                </div>
                                                <div class="modal-body p-0" v-if="selectedRoom">
                                                    <div class="row g-0">
                                                        <div class="col-md-6 bg-light" style="min-height: 300px;">
                                                            <img :src="getRoomImage(selectedRoom)" class="w-100 h-100 object-fit-cover" :alt="selectedRoom.room_type" @error="onImageError($event, selectedRoom.room_type)">
                                                        </div>
                                                        <div class="col-md-6 p-4 p-lg-5 d-flex flex-column">
                                                            <span class="text-gold text-uppercase letter-spacing-wide small fw-bold mb-2">{{ selectedRoom.room_type }}</span>
                                                            <h3 class="serif-font mb-3">Room #{{ selectedRoom.room_number }}</h3>
                                                            
                                                            <div class="d-flex align-items-center gap-3 mb-4 text-muted small">
                                                                 <span v-if="selectedRoom.room_size"><i class="bi bi-arrows-fullscreen me-1"></i> {{ selectedRoom.room_size }} m²</span>
                                                                 <span v-if="selectedRoom.bed_type"><i class="bi bi-hdd-stack me-1"></i> {{ selectedRoom.bed_type }}</span>
                                                                 <span><i class="bi bi-people me-1"></i> Max {{ selectedRoom.max_occupancy }} Guests</span>
                                                            </div>

                                                            <p class="text-muted mb-4 small flex-grow-1">{{ selectedRoom.description }}</p>
                                                            
                                                            <div class="mb-4" v-if="selectedRoom.amenities && selectedRoom.amenities.length">
                                                                <p class="small fw-bold text-uppercase text-muted mb-2">Amenities</p>
                                                                <div class="d-flex flex-wrap gap-2">
                                                                    <span v-for="amenity in selectedRoom.amenities" :key="amenity.id" class="badge bg-light text-dark fw-normal py-2 px-3 rounded-pill border">
                                                                        <i :class="['bi', amenity.icon, 'text-gold me-1']"></i> {{ amenity.name }}
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="pt-3 border-top mt-auto">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <small class="text-muted text-uppercase fw-bold d-block">
                                                                            {{ (selectedRoom.room_type === 'Family Room' || selectedRoom.room_type === 'Barkadahan Room') ? 'Price per head / night' : 'Price per night' }}
                                                                        </small>
                                                                        <span v-if="selectedRoom.is_promo" class="badge bg-danger text-white x-small mt-1">
                                                                            <i class="bi bi-tag-fill me-1"></i> {{ selectedRoom.promo_title || 'Promo Special' }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <template v-if="selectedRoom.is_promo">
                                                                            <span class="h2 serif-font text-gold mb-0">₱{{ formatPrice(effectiveRate) }}</span>
                                                                            <span class="text-muted text-decoration-line-through small d-block">₱{{ formatPrice(originalRate) }}</span>
                                                                        </template>
                                                                        <template v-else>
                                                                            <span class="h2 serif-font text-gold mb-0">₱{{ formatPrice(originalRate) }}</span>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Teleport>
                                
                                <div class="col-12">
                                    <label class="form-label-custom mb-1">Number of Guests</label>
                                    <GuestSelector 
                                        v-model="booking.guests"
                                        v-model:adultsValue="booking.adults"
                                        v-model:childrenValue="booking.children"
                                        v-model:childrenAgesValue="booking.childrenAges"
                                        :maxOccupancy="Number(selectedRoom?.max_occupancy || 4)"
                                        :minOccupancy="Number(selectedRoom?.min_occupancy || 1)"
                                        triggerClass="form-control-custom w-100 px-3 py-2.5"
                                    />
                                </div>

                                <!-- Extra Bed Option (depends on selected room) -->
                                <div v-if="selectedRoom && !selectedPackage && maxExtraBeds > 0" class="col-12 animate-fade-in">
                                    <label class="form-label-custom mb-1">Extra Bed (Optional)</label>
                                    <div class="extra-bed-box form-control-custom w-100 px-3 py-2 d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <i class="bi bi-plus-circle text-gold fs-5 flex-shrink-0"></i>
                                            <div class="text-truncate">
                                                <div class="fw-semibold text-secondary-dark small text-truncate">
                                                    {{ booking.extraBeds > 0 ? `${booking.extraBeds} Extra Bed${booking.extraBeds > 1 ? 's' : ''}` : 'Add Extra Bed' }}
                                                </div>
                                                <div class="x-small text-muted">
                                                    ₱{{ formatPrice(extraBedPrice) }} / night · Max {{ maxExtraBeds }} for {{ selectedRoom.room_type }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                            <button
                                                type="button"
                                                class="btn-extra-bed-step"
                                                :disabled="booking.extraBeds <= 0"
                                                @click="decrementExtraBeds"
                                                aria-label="Decrease extra beds"
                                            >
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <span class="extra-bed-count fw-bold text-secondary-dark">{{ booking.extraBeds }}</span>
                                            <button
                                                type="button"
                                                class="btn-extra-bed-step"
                                                :disabled="booking.extraBeds >= maxExtraBeds"
                                                @click="incrementExtraBeds"
                                                aria-label="Increase extra beds"
                                            >
                                                <i class="bi bi-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="select-date-section pt-4 border-top">
                                <h2 class="serif-font h3 mb-4 text-secondary-dark">Select Your Dates</h2>
                                <div class="row g-4">
                                    <!-- Check-in Calendar Box -->
                                    <div class="col-md-6">
                                        <label class="form-label-custom mb-3">Check-in Date</label>
                                        <div class="calendar-container">
                                            <CalendarPicker 
                                              v-model="booking.checkIn" 
                                              :min-date="formatToYMD(new Date())" 
                                              :disabled-dates="bookedDates"
                                            />
                                            <input type="date" class="form-control form-control-custom mt-3 border-0 bg-light text-center" v-model="booking.checkIn">
                                        </div>
                                     </div>
                                     <!-- Check-out Calendar Box -->
                                     <div class="col-md-6">
                                         <label class="form-label-custom mb-3">Check-out Date</label>
                                         <div class="calendar-container">
                                             <CalendarPicker 
                                              v-model="booking.checkOut" 
                                              :min-date="booking.checkIn || formatToYMD(new Date())" 
                                              :max-date="maxCheckOutDate"
                                              :disabled-dates="bookedDates"
                                              :is-checkout="true"
                                            />
                                            <input type="date" class="form-control form-control-custom mt-3 border-0 bg-light text-center" v-model="booking.checkOut">
                                        </div>
                                     </div>

                                     <!-- Unavailable & Blocked Dates List -->
                                     <div v-if="bookedDates && bookedDates.length > 0" class="col-12 mt-4 animate-fade-up">
                                         <div class="d-flex align-items-center gap-2 mb-2">
                                             <div class="p-1 bg-warning-subtle rounded-circle">
                                                 <i class="bi bi-calendar-x text-warning"></i>
                                             </div>
                                             <h6 class="mb-0 fw-bold text-dark small text-uppercase tracking-wider">
                                                 {{ booking.roomId ? 'Unavailable & Blocked Dates for this Room' : 'Unavailable & Blocked Dates' }}
                                             </h6>
                                         </div>
                                         <div class="booked-dates-list p-3 rounded-4 bg-light border">
                                             <div v-for="(res, idx) in bookedDates" :key="idx" class="booked-date-item d-flex justify-content-between align-items-center mb-2 last-mb-0">
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
                                     </div>
                                </div>
                            </div>

                            <button @click="nextStep" :disabled="isSuspended" class="btn btn-gold w-100 py-3 mt-5 text-uppercase fw-bold letter-spacing-wide shadow-none continue-btn">
                                {{ isSuspended ? 'Account Suspended' : 'Continue to Guest Details' }}
                            </button>
                            <p v-if="isSuspended" class="text-danger small text-center mt-3 fw-bold">
                               <i class="bi bi-exclamation-triangle-fill me-1"></i> 
                               Your account is restricted from making new bookings.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2: Guest Details -->
                    <div v-if="currentStep === 2" class="booking-section animate-fade-up">
                        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 booking-main-card">
                            <h2 class="serif-font h3 mb-4 text-secondary-dark">Guest Information</h2>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label-custom">First Name</label>
                                    <input type="text" class="form-control form-control-custom py-3" v-model="booking.firstName" placeholder="Enter first name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Last Name</label>
                                    <input type="text" class="form-control form-control-custom py-3" v-model="booking.lastName" placeholder="Enter last name">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label-custom">Email Address</label>
                                    <input type="email" class="form-control form-control-custom py-3" v-model="booking.email" placeholder="example@email.com">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label-custom">Phone Number</label>
                                    <input type="tel" class="form-control form-control-custom py-3" v-model="booking.phone" placeholder="+63 9xx xxx xxxx">
                                </div>
                                <div class="col-12">
                                    <label class="form-label-custom">Special Requests (Optional)</label>
                                    <textarea class="form-control form-control-custom" rows="3" placeholder="If you have any special requirements, please let us know..."></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-3 mt-5">
                                <button @click="prevStep" class="btn btn-outline-dark-custom flex-grow-1 py-3 text-uppercase fw-bold">
                                    Back
                                </button>
                                <button @click="nextStep" :disabled="isSuspended" class="btn btn-gold flex-grow-2 w-100 py-3 text-uppercase fw-bold letter-spacing-wide shadow-none continue-btn">
                                    {{ isSuspended ? 'Restricted' : 'Review Booking' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Review & Confirm -->
                    <div v-if="currentStep === 3" class="booking-section animate-fade-up">
                        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 booking-main-card">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-3 border-bottom">
                                <div>
                                    <h2 class="serif-font h3 mb-1 text-secondary-dark">Review Your Booking</h2>
                                    <p class="small text-muted mb-0">Confirm your details below to generate your booking receipt.</p>
                                </div>
                                <span class="badge bg-gold-subtle text-gold px-3 py-2 rounded-pill fw-bold small">
                                    Pay at the Apartelle
                                </span>
                            </div>

                            <!-- Guest & Stay Overview -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4 bg-light border h-100">
                                        <span class="d-block x-small text-uppercase fw-bold text-muted mb-2">Guest Information</span>
                                        <div class="fw-bold text-secondary-dark mb-1">{{ booking.firstName }} {{ booking.lastName }}</div>
                                        <div class="small text-muted">{{ booking.email }}</div>
                                        <div class="small text-muted">{{ booking.phone }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4 bg-light border h-100">
                                        <span class="d-block x-small text-uppercase fw-bold text-muted mb-2">Stay Details</span>
                                        <div class="fw-bold text-secondary-dark mb-1" v-if="selectedRoom">
                                            {{ selectedRoom.room_type }} #{{ selectedRoom.room_number }}
                                        </div>
                                        <div class="small text-muted">
                                            {{ booking.checkIn }} &mdash; {{ booking.checkOut }} ({{ totalNights }} night{{ totalNights > 1 ? 's' : '' }})
                                        </div>
                                        <div class="small text-muted">
                                            {{ booking.adults || booking.guests }} Adult{{ (booking.adults || booking.guests) > 1 ? 's' : '' }}<template v-if="booking.children > 0"> &middot; {{ booking.children }} Child{{ booking.children > 1 ? 'ren' : '' }}</template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Option -->
                            <div class="mb-4">
                                <label class="form-label x-small text-uppercase fw-bold text-muted mb-2 tracking-wider d-block">Payment Option</label>
                                <div class="d-flex gap-2">
                                    <button
                                        type="button"
                                        class="btn flex-grow-1 py-2 px-3 fw-bold small text-uppercase"
                                        :class="booking.paymentOption === 'full' ? 'btn-gold text-white shadow-sm' : 'btn-outline-gold'"
                                        @click="booking.paymentOption = 'full'"
                                        style="font-size: 0.75rem; border-radius: 12px; height: 42px;"
                                    >
                                        Full Payment
                                    </button>
                                    <button
                                        type="button"
                                        class="btn flex-grow-1 py-2 px-3 fw-bold small text-uppercase"
                                        :class="booking.paymentOption === 'half' ? 'btn-gold text-white shadow-sm' : 'btn-outline-gold'"
                                        @click="booking.paymentOption = 'half'"
                                        style="font-size: 0.75rem; border-radius: 12px; height: 42px;"
                                    >
                                        50% Downpayment
                                    </button>
                                </div>
                            </div>

                            <!-- Total Amount Highlight -->
                            <div class="p-4 rounded-4 border mb-4" style="background-color: #fdfbf7; border-color: rgba(188, 145, 81, 0.3) !important;">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="d-block x-small text-uppercase fw-bold text-muted">
                                            {{ booking.paymentOption === 'half' ? '50% Downpayment Due Now' : 'Total Amount to Settle' }}
                                        </span>
                                        <small class="text-muted" v-if="booking.paymentOption === 'half'">
                                            Total: ₱{{ formatPrice(totalPrice) }} &middot; Pay remaining ₱{{ formatPrice(totalPrice / 2) }} when you walk in
                                        </small>
                                        <small class="text-muted" v-else>Taxes and service fees included</small>
                                    </div>
                                    <div class="h2 mb-0 serif-font fw-bold text-gold">₱{{ formatPrice(amountDue) }}</div>
                                </div>
                            </div>

                            <!-- Pay at Apartelle Notice -->
                            <div class="alert alert-light border shadow-sm d-flex align-items-start gap-3 rounded-4 p-4 mb-0">
                                <div class="icon-box-gold mt-1 flex-shrink-0" style="background: rgba(188, 145, 81, 0.1); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #BC9151;">
                                    <i class="bi bi-receipt fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-secondary-dark mb-1">
                                        {{ booking.paymentOption === 'half' ? '50% Downpayment via GCash QR' : 'Full Payment via GCash QR' }}
                                    </h6>
                                    <p class="small text-muted mb-0" style="line-height: 1.6;">
                                        <template v-if="booking.paymentOption === 'half'">
                                            Pay the 50% downpayment (₱{{ formatPrice(amountDue) }}) via GCash QR to submit your booking, then pay the remaining ₱{{ formatPrice(totalPrice / 2) }} when you walk in.
                                        </template>
                                        <template v-else>
                                            Once you confirm, scan the GCash QR code and upload your payment screenshot to submit your booking and generate your official receipt.
                                        </template>
                                    </p>
                                </div>
                            </div>

                            <div class="d-flex gap-3 mt-5">
                                <button @click="prevStep" class="btn btn-outline-dark-custom flex-grow-1 py-3 text-uppercase fw-bold">
                                    Back
                                </button>
                                <button @click="handleBooking" :disabled="loading || isSuspended" class="btn btn-gold flex-grow-2 w-100 py-3 text-uppercase fw-bold letter-spacing-wide shadow-none continue-btn">
                                    <template v-if="isSuspended">Account Suspended</template>
                                    <template v-else>{{ loading ? 'Confirming...' : `Confirm Booking - ₱${formatPrice(amountDue)}` }}</template>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Summary -->
                <div class="col-lg-4">
                    <div class="booking-summary-sidebar sticky-top" style="top: 100px; z-index: 10;">
                        <div class="card border-0 shadow-sm rounded-4 p-4 overflow-hidden">
                            <h3 class="serif-font h4 mb-4 text-secondary-dark border-bottom pb-3">Booking Summary</h3>
                            <div class="summary-details">
                                <div class="summary-item mb-3">
                                    <span class="label text-muted small text-uppercase fw-bold">Check-in</span>
                                    <span class="value fs-6 fw-bold text-secondary-dark d-block mt-1">{{ booking.checkIn || '-' }}</span>
                                </div>
                                <div class="summary-item mb-3">
                                    <span class="label text-muted small text-uppercase fw-bold">Check-out</span>
                                    <span class="value fs-6 fw-bold text-secondary-dark d-block mt-1">{{ booking.checkOut || '-' }}</span>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <span class="label text-muted small text-uppercase fw-bold">Nights</span>
                                        <span class="value fw-bold text-secondary-dark d-block mt-1">{{ totalNights || '-' }}</span>
                                    </div>
                                    <div class="col-6">
                                        <span class="label text-muted small text-uppercase fw-bold">Guests</span>
                                        <span class="value fw-bold text-secondary-dark d-block mt-1">
                                            {{ booking.adults || booking.guests }} Adult{{ (booking.adults || booking.guests) > 1 ? 's' : '' }}
                                            <template v-if="booking.children > 0">
                                                · {{ booking.children }} Child{{ booking.children > 1 ? 'ren' : '' }}
                                            </template>
                                        </span>
                                    </div>
                                </div>
                                <div class="summary-item mb-3.5" v-if="selectedPackage">
                                    <span class="label text-muted small text-uppercase fw-bold">Package</span>
                                    <div class="package-summary-card rounded-3 mt-2 border" style="padding: 14px 16px; background-color: #f8fafc; border-color: #e2e8f0 !important;">
                                        <div class="fw-bold text-secondary-dark d-flex align-items-center gap-2 mb-1.5" style="font-size: 0.92rem; line-height: 1.35;">
                                            <i class="bi bi-box2-heart-fill text-gold fs-6 flex-shrink-0"></i>
                                            <span>{{ selectedPackage.name }}</span>
                                        </div>
                                        <div class="text-muted ps-4" style="font-size: 0.8rem; line-height: 1.45; color: #64748b !important;">
                                            {{ selectedPackage.duration }} · {{ selectedPackage.pax }}
                                        </div>
                                    </div>
                                </div>
                                <div class="summary-item mt-3" v-if="selectedRoom">
                                    <span class="label text-muted small text-uppercase fw-bold">Room</span>
                                    <span class="value fw-bold text-secondary-dark d-block mt-1">{{ selectedRoom.room_type }} #{{ selectedRoom.room_number }}</span>
                                </div>
                                <div class="summary-item mt-3" v-if="extraGuestsCount > 0">
                                    <span class="label text-muted small text-uppercase fw-bold">Extra Bed</span>
                                    <span class="value fw-bold text-gold d-block mt-1">{{ extraGuestsCount }} Extra Bed{{ extraGuestsCount > 1 ? 's' : '' }} (₱{{ formatPrice(extraBedPrice) }}/night)</span>
                                </div>
                                
                                <div class="total-section mt-4 pt-4 border-top">
                                     <template v-if="selectedPackage">
                                         <div class="d-flex justify-content-between align-items-center mb-1">
                                             <span class="h6 mb-0 text-muted">Tour Package Total</span>
                                             <span class="h6 mb-0 serif-font text-dark fw-bold">₱{{ formatPrice(selectedPackage.price) }}</span>
                                         </div>
                                         <div class="d-flex justify-content-end mb-2">
                                             <small class="text-muted italic-text text-end" style="font-size: 0.75rem; font-style: italic;">
                                                 All-inclusive fixed rate (Room + Island Hopping)
                                             </small>
                                         </div>
                                     </template>
                                     <template v-else>
                                         <div class="d-flex justify-content-between align-items-center mb-1">
                                             <span class="h6 mb-0 text-muted">Base Room Rate</span>
                                             <span class="h6 mb-0 serif-font text-dark fw-bold">₱{{ formatPrice(basePriceTotal) }}</span>
                                         </div>
                                         <div class="d-flex justify-content-end mb-2" v-if="selectedRoom && totalNights > 0">
                                             <small class="text-muted italic-text text-end" style="font-size: 0.75rem; font-style: italic;">
                                                 <template v-if="selectedRoom.room_type === 'Family Room' || selectedRoom.room_type === 'Barkadahan Room'">
                                                     ((₱{{ formatPrice(effectiveRate) }} × {{ chargeableGuests }} paying guest{{ chargeableGuests > 1 ? 's' : '' }}) × {{ totalNights }} night{{ totalNights > 1 ? 's' : '' }})
                                                 </template>
                                                 <template v-else>
                                                     (₱{{ formatPrice(effectiveRate) }} × {{ totalNights }} night{{ totalNights > 1 ? 's' : '' }})
                                                 </template>
                                                 <span v-if="selectedRoom.is_promo" class="badge bg-danger text-white ms-1 x-small py-0 px-1">PROMO</span>
                                             </small>
                                         </div>

                                         <!-- Free Children (Under 7) Line Item if applicable -->
                                         <div v-if="freeChildrenCount > 0" class="d-flex justify-content-between align-items-center mb-2 text-success">
                                             <span class="small fw-semibold d-flex align-items-center gap-1">
                                                 <i class="bi bi-gift-fill"></i>
                                                 Children under 7 ({{ freeChildrenCount }} child{{ freeChildrenCount > 1 ? 'ren' : '' }})
                                             </span>
                                             <span class="small fw-bold">FREE (₱0)</span>
                                         </div>

                                         <!-- Additional Beddings Line Item if applicable -->
                                         <div v-if="extraGuestsCount > 0 && totalNights > 0" class="d-flex justify-content-between align-items-center mb-2">
                                             <span class="small fw-semibold text-gold-dark">
                                                 <i class="bi bi-plus-circle me-1"></i>
                                                 Extra Bed ({{ extraGuestsCount }} × ₱{{ formatPrice(extraBedPrice) }} × {{ totalNights }}n)
                                             </span>
                                             <span class="small fw-bold text-gold-dark">+₱{{ formatPrice(extraBeddingTotal) }}</span>
                                         </div>
                                     </template>

                                     <div class="d-flex justify-content-between align-items-center mb-2 pt-2 border-top">
                                         <span class="h6 mb-0 text-dark fw-bold">Total Amount</span>
                                         <span class="h5 mb-0 serif-font text-gold fw-bold">₱{{ formatPrice(totalPrice) }}</span>
                                     </div>
                                     <template v-if="booking.paymentOption === 'half'">
                                         <div class="d-flex justify-content-between align-items-center mb-1">
                                             <span class="small fw-semibold text-success">50% Downpayment Due</span>
                                             <span class="small fw-bold text-success">₱{{ formatPrice(amountDue) }}</span>
                                         </div>
                                         <div class="d-flex justify-content-between align-items-center mb-2">
                                             <span class="small text-muted">Remaining Balance (Walk-in)</span>
                                             <span class="small fw-bold text-dark">₱{{ formatPrice(totalPrice / 2) }}</span>
                                         </div>
                                     </template>
                                     <p class="small text-muted text-center mt-3 mb-0">
                                         {{ booking.paymentOption === 'half' ? 'Pay 50% now · Pay the other half when you walk in' : 'Taxes and fees included' }}
                                     </p>
                                 </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-4 rounded-4 bg-white shadow-sm border border-light animate-fade-up">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-box-gold" style="width: 40px; height: 40px;">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">100% Secure</h6>
                                    <span class="small text-muted">Your data is safe with us</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <QrPaymentModal
            :show="showQrPaymentModal"
            :amount="amountDue"
            :room-label="selectedRoom ? `Room #${selectedRoom.room_number} (${selectedRoom.room_type})` : ''"
            :check-in="booking.checkIn"
            :check-out="booking.checkOut"
            :qr-url="publicSettings.gcash_qr_url"
            :gcash-number="publicSettings.gcash_number"
            :gcash-account-name="publicSettings.gcash_account_name"
            :submitting="loading"
            @close="showQrPaymentModal = false"
            @confirm="submitBookingWithProof"
        />
    </div>
</template>

<style scoped>
.booking-page {
    line-height: 1.6;
    padding-top: 100px;
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

.section-label-alt {
    color: var(--primary-gold);
    letter-spacing: 4px;
    font-size: 0.8rem;
    font-weight: 700;
}

.booking-steps {
    display: flex;
    align-items: center;
}

.step-item {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    background: #f0f0f0;
    border-radius: var(--radius-full);
    transition: all var(--transition-normal);
}

.step-item.active {
    background: var(--primary-gold);
}

.step-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #888;
}

.step-label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #888;
    white-space: nowrap;
}

.step-item.active .step-icon,
.step-item.active .step-label {
    color: white;
}

.step-line {
    height: 1px;
    background-color: #ddd;
    flex-grow: 1;
    max-width: 60px;
    margin: 0 1rem;
}

@media (max-width: 992px) {
    .booking-steps {
        overflow-x: auto;
        justify-content: flex-start !important;
        padding-bottom: 1rem;
    }
}

/* Calendar Mock UI */
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-head {
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: #aaa;
    padding: 8px 0;
}

.calendar-date {
    text-align: center;
    padding: 10px 0;
    font-size: 0.85rem;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
    user-select: none;
}

.calendar-date:hover:not(.empty):not(.disabled) {
    background-color: var(--primary-gold-subtle);
}

.calendar-date.selected {
    background-color: var(--primary-gold) !important;
    color: white !important;
    font-weight: 700;
}

.calendar-date.disabled {
    color: #d1d1d1;
    cursor: not-allowed;
    pointer-events: none;
}

.calendar-date.empty {
    cursor: default;
}

.booking-main-card {
    border: 1px solid rgba(0,0,0,0.03) !important;
}

.continue-btn {
    background: var(--primary-gold-light) !important;
    border-color: var(--primary-gold-light) !important;
    color: #8D6E63 !important; /* Muted brown from screenshot */
}

.continue-btn:hover {
    background: var(--primary-gold) !important;
    color: white !important;
}

.form-control-custom {
    border: 1px solid #eee !important;
}

.payment-card-method {
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
}

.payment-card-method:hover {
    border-color: var(--primary-gold-light) !important;
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(188, 145, 81, 0.05);
}

.payment-card-method.active {
    border-color: var(--primary-gold) !important;
    background: var(--primary-gold-subtle);
}

.animate-pop {
    animation: pop 0.3s cubic-bezier(0.26, 0.53, 0.74, 1.48);
}

@keyframes pop {
    0% { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.icon-box-gold {
    background: var(--primary-gold-subtle);
    color: var(--primary-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.flex-grow-2 {
    flex-grow: 2;
}

.booking-summary-sidebar .card {
    border: 1px solid rgba(0,0,0,0.03) !important;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.booked-dates-list {
    max-height: 180px;
    overflow-y: auto;
}

.booked-dates-list::-webkit-scrollbar {
    width: 4px;
}

.booked-dates-list::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
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

.extra-bed-box {
    background-color: #ffffff;
    border-radius: 14px;
    min-height: 56px;
}

.btn-extra-bed-step {
    width: 32px;
    height: 32px;
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
    min-width: 22px;
    text-align: center;
    font-size: 0.95rem;
}
</style>
