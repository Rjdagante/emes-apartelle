<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import Swal from "sweetalert2";
import CalendarPicker from "../components/CalendarPicker.vue";
import GuestSelector from "../components/GuestSelector.vue";

const router = useRouter();

// Get today's date in YYYY-MM-DD format for min date constraint
const today = computed(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
});

// Real API Data
const allRooms = ref([]);
const rooms = ref([]);
const amenities = ref([]);
const loadingRooms = ref(true);
const checking = ref(false);

// Promotional Room Advertisement State
const promoRooms = computed(() => allRooms.value.filter((r) => r.is_promo));
const activePromoIndex = ref(0);
const activePromoRoom = computed(() => {
    if (promoRooms.value.length === 0) return null;
    return promoRooms.value[activePromoIndex.value] || promoRooms.value[0];
});

let promoInterval = null;
const startPromoTimer = () => {
    stopPromoTimer();
    if (promoRooms.value.length > 1) {
        promoInterval = setInterval(() => {
            nextPromo();
        }, 5000); // 5-second automatic slide interval
    }
};

const stopPromoTimer = () => {
    if (promoInterval) {
        clearInterval(promoInterval);
        promoInterval = null;
    }
};

const nextPromo = () => {
    if (promoRooms.value.length <= 1) return;
    activePromoIndex.value =
        (activePromoIndex.value + 1) % promoRooms.value.length;
    startPromoTimer();
};

const prevPromo = () => {
    if (promoRooms.value.length <= 1) return;
    activePromoIndex.value =
        (activePromoIndex.value - 1 + promoRooms.value.length) %
        promoRooms.value.length;
    startPromoTimer();
};

const setPromoIndex = (index) => {
    activePromoIndex.value = index;
    startPromoTimer();
};

// Tour Packages Advertisement State
const packages = ref([]);
const activePackageIndex = ref(0);
const activePackage = computed(() => {
    if (packages.value.length === 0) return null;
    return packages.value[activePackageIndex.value] || packages.value[0];
});

let packageInterval = null;
const startPackageTimer = () => {
    stopPackageTimer();
    if (packages.value.length > 1) {
        packageInterval = setInterval(() => {
            nextPackage();
        }, 6000); // 6-second automatic slide interval
    }
};

const stopPackageTimer = () => {
    if (packageInterval) {
        clearInterval(packageInterval);
        packageInterval = null;
    }
};

const nextPackage = () => {
    if (packages.value.length <= 1) return;
    activePackageIndex.value =
        (activePackageIndex.value + 1) % packages.value.length;
    startPackageTimer();
};

const prevPackage = () => {
    if (packages.value.length <= 1) return;
    activePackageIndex.value =
        (activePackageIndex.value - 1 + packages.value.length) %
        packages.value.length;
    startPackageTimer();
};

const fetchPackages = async () => {
    try {
        const response = await axios.get("/api/room-packages/public");
        packages.value = (response.data || []).filter(
            (p) => p.is_active !== false,
        );
        startPackageTimer();
    } catch (error) {
        console.error("Failed to fetch packages:", error);
    }
};

// Guest Package Modal State for Homepage
const showGuestPackageModal = ref(false);
const selectedGuestPackage = ref(null);

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
        path: "/book-now",
        query: {
            packageId: pkg.id,
            roomId: pkg.room_id || "",
        },
    });
};

const getPackageImage = (pkg) => {
    if (!pkg) return "/images/unsplash/hero-resort.jpg";
    if (pkg.image_url) return pkg.image_url;
    if (pkg.image) {
        return pkg.image.startsWith("http") || pkg.image.startsWith("/")
            ? pkg.image
            : `/storage/${pkg.image}`;
    }
    return "/images/unsplash/hero-resort.jpg";
};

const isPerHead = (room) => {
    if (!room) return false;
    return (
        room.room_type === "Family Room" || room.room_type === "Barkadahan Room"
    );
};

const getEffectiveRate = (room) => {
    if (!room) return 0;
    if (room.is_promo) {
        if (
            isPerHead(room) &&
            room.promo_price_per_head !== null &&
            Number(room.promo_price_per_head) > 0
        ) {
            return Number(room.promo_price_per_head);
        }
        if (
            !isPerHead(room) &&
            room.promo_price !== null &&
            Number(room.promo_price) > 0
        ) {
            return Number(room.promo_price);
        }
    }
    return isPerHead(room)
        ? Number(room.price_per_head || 0)
        : Number(room.price_per_night || 0);
};

const getRegularRate = (room) => {
    if (!room) return 0;
    return isPerHead(room)
        ? Number(room.price_per_head || 0)
        : Number(room.price_per_night || 0);
};

const getPromoSavings = (room) => {
    if (!room) return 0;
    const regular = getRegularRate(room);
    const effective = getEffectiveRate(room);
    return Math.max(0, regular - effective);
};

const sortRoomsByNumber = (list) => {
    if (!Array.isArray(list)) return [];
    return [...list].sort((a, b) => {
        return String(a.room_number || "").localeCompare(
            String(b.room_number || ""),
            undefined,
            {
                numeric: true,
                sensitivity: "base",
            },
        );
    });
};

const fetchFeaturedRooms = async () => {
    loadingRooms.value = true;
    try {
        const response = await axios.get("/api/rooms");
        const sorted = sortRoomsByNumber(response.data);
        allRooms.value = sorted;
        // Take first 3 rooms for the homepage features
        rooms.value = sorted.slice(0, 3);
        startPromoTimer();
    } catch (error) {
        console.error("Failed to fetch rooms:", error);
    } finally {
        loadingRooms.value = false;
    }
};

const fetchAmenities = async () => {
    try {
        const response = await axios.get("/api/amenities");
        // Only show active amenities on the homepage
        amenities.value = response.data.filter((a) => a.is_active);
    } catch (error) {
        console.error("Failed to fetch amenities:", error);
    }
};

const showAllAmenities = ref(false);

const displayedAmenities = computed(() => {
    if (!amenities.value || !Array.isArray(amenities.value)) return [];
    if (showAllAmenities.value) {
        return amenities.value;
    }
    return amenities.value.slice(0, 8);
});

const toggleAmenities = () => {
    if (showAllAmenities.value) {
        showAllAmenities.value = false;
        const section = document.querySelector(".amenities-section");
        if (section) {
            section.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    } else {
        showAllAmenities.value = true;
    }
};

// Booking Form Data
const booking = ref({
    checkIn: "",
    checkOut: "",
    guests: 1,
    adults: 1,
    children: 0,
    childrenAges: [],
});

const blockedDates = ref([]);
const showCheckInCalendar = ref(false);
const showCheckOutCalendar = ref(false);
const checkInWrapper = ref(null);
const checkOutWrapper = ref(null);

const toggleCheckIn = () => {
    showCheckInCalendar.value = !showCheckInCalendar.value;
    if (showCheckInCalendar.value) {
        showCheckOutCalendar.value = false;
    }
};

const toggleCheckOut = () => {
    showCheckOutCalendar.value = !showCheckOutCalendar.value;
    if (showCheckOutCalendar.value) {
        showCheckInCalendar.value = false;
    }
};

const onCheckInSelected = (val) => {
    showCheckInCalendar.value = false;
    if (!booking.value.checkOut || booking.value.checkOut <= val) {
        showCheckOutCalendar.value = true;
    }
};

const maxCheckOutDate = computed(() => {
    if (!booking.value.checkIn || !blockedDates.value.length) return null;
    const checkInDate = new Date(booking.value.checkIn + "T00:00:00");

    let firstNextDate = null;
    blockedDates.value.forEach((range) => {
        const startStr = (range.check_in || range.start || "").split(" ")[0];
        if (startStr) {
            const start = new Date(startStr + "T00:00:00");
            if (start >= checkInDate) {
                if (
                    !firstNextDate ||
                    start < new Date(firstNextDate.split(" ")[0] + "T00:00:00")
                ) {
                    firstNextDate = range.check_in || range.start;
                }
            }
        }
    });

    return firstNextDate ? firstNextDate.split(" ")[0] : null;
});

watch(
    () => booking.value.checkIn,
    (newCheckIn) => {
        if (booking.value.checkOut) {
            if (newCheckIn && booking.value.checkOut <= newCheckIn) {
                booking.value.checkOut = "";
            } else if (
                maxCheckOutDate.value &&
                booking.value.checkOut > maxCheckOutDate.value
            ) {
                booking.value.checkOut = "";
            }
        }
    },
);

const fetchBookedDates = async () => {
    try {
        const response = await axios.get("/api/rooms/booked-dates");
        blockedDates.value = Array.isArray(response.data) ? response.data : [];
    } catch (err) {
        console.error("Error fetching booked dates:", err);
        blockedDates.value = [];
    }
};

const handleClickOutside = (e) => {
    if (checkInWrapper.value && !checkInWrapper.value.contains(e.target)) {
        showCheckInCalendar.value = false;
    }
    if (checkOutWrapper.value && !checkOutWrapper.value.contains(e.target)) {
        showCheckOutCalendar.value = false;
    }
};

onMounted(() => {
    fetchFeaturedRooms();
    fetchPackages();
    fetchAmenities();
    fetchBookedDates();
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    stopPromoTimer();
    stopPackageTimer();
    document.removeEventListener("click", handleClickOutside);
});

// Testimonials Data
const testimonials = ref([
    {
        name: "Margette Revelo",
        role: "Business Traveler",
        image: "/images/margette.png",
        text: "The staff was incredibly welcoming and the room was spotless. Perfect location for my business trip. Will definitely book again!",
        rating: 5,
    },
    {
        name: "RJ Estodillo",
        role: "Family Vacation",
        image: "/images/avatars/testimonial-male.jpg",
        text: "Our family had an amazing stay. The suite was spacious, clean, and had everything we needed. The kids loved it!",
        rating: 5,
    },
    {
        name: "Nico Monderno",
        role: "Solo Traveler",
        image: "/images/avatars/testimonial-female.jpg",
        text: "Best value for money in the area. The breakfast was delicious and the WiFi was fast. Highly recommended!",
        rating: 5,
    },
]);

const showRoomsModal = ref(false);
const availableRooms = ref([]);

const formatDisplayDate = (dateStr) => {
    if (!dateStr) return "";
    const date = new Date(dateStr + "T00:00:00");
    return date.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
};

const totalNights = computed(() => {
    if (!booking.value.checkIn || !booking.value.checkOut) return 0;
    const start = new Date(booking.value.checkIn);
    const end = new Date(booking.value.checkOut);
    const diff = end - start;
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
});

const formatPrice = (price) => {
    return parseFloat(price || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
};

const closeRoomsModal = () => {
    showRoomsModal.value = false;
};

const bookThisRoom = (roomId) => {
    showRoomsModal.value = false;
    router.push({
        path: `/rooms/${roomId}`,
        query: {
            checkIn: booking.value.checkIn,
            checkOut: booking.value.checkOut,
            guests: booking.value.guests,
            adults: booking.value.adults,
            children: booking.value.children,
            childrenAges: JSON.stringify(booking.value.childrenAges || []),
        },
    });
};

const handleCheckAvailability = async () => {
    if (!booking.value.checkIn || !booking.value.checkOut) {
        Swal.fire({
            icon: "warning",
            title: "Dates Required",
            text: "Please select check-in and check-out dates.",
            confirmButtonColor: "#BC9151",
        });
        return;
    }

    checking.value = true;
    try {
        const response = await axios.get("/api/rooms", {
            params: {
                check_in: booking.value.checkIn,
                check_out: booking.value.checkOut,
            },
        });

        const filtered = response.data.filter((r) => r.status === "available");

        if (filtered.length > 0) {
            availableRooms.value = filtered;
            showRoomsModal.value = true;
        } else {
            Swal.fire({
                icon: "error",
                title: "No Availability",
                text: "Sorry, we are fully booked for these dates.",
                confirmButtonColor: "#BC9151",
            });
        }
    } catch (e) {
        console.error(e);
        Swal.fire({
            icon: "error",
            title: "System Error",
            text: "Unable to check availability. Please try again.",
            confirmButtonColor: "#BC9151",
        });
    } finally {
        checking.value = false;
    }
};

const getDefaultImage = (type) => {
    const t = (type || "").toLowerCase();
    if (t.includes("family") || t.includes("suite"))
        return "/images/unsplash/suite-room.jpg";
    if (t.includes("barkadahan") || t.includes("deluxe"))
        return "/images/unsplash/deluxe-room.jpg";
    return "/images/unsplash/standard-room.jpg";
};

const getRoomImage = (room) => {
    if (!room) return "/images/unsplash/standard-room.jpg";
    if (room.image && room.image !== "null" && room.image !== "")
        return room.image;
    if (room.images && room.images.length > 0) {
        const first = room.images.find(
            (img) => img.image_path && img.image_path !== "null",
        );
        if (first) return first.image_path;
    }
    return getDefaultImage(room.room_type);
};

const onImageError = (e, roomType) => {
    e.target.src = getDefaultImage(roomType);
};
const formatIconClass = (icon) => {
    if (!icon) return "";
    return icon.startsWith("bi-") ? icon : `bi-${icon}`;
};
</script>

<template>
    <div class="home-page">
        <!-- ========== HERO SECTION ========== -->
        <section class="hero-section">
            <div class="hero-overlay"></div>
            <div class="container position-relative z-1 py-3 py-lg-0">
                <div class="row align-items-center hero-content-row">
                    <!-- Left Content -->
                    <div class="col-lg-7 hero-content pe-lg-4 mb-4 mb-lg-0">
                        <!-- Trust Badge -->
                        <div
                            class="trust-badge animate-fade-up d-inline-flex align-items-center gap-2.5 bg-white-10 py-1.5 px-3 rounded-pill mb-3 border border-white-10 backdrop-blur"
                        >
                            <span class="stars-gold">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </span>
                            <span
                                class="trust-text text-white small fw-bold tracking-wide"
                                >Trusted by 500+ Guests</span
                            >
                        </div>

                        <!-- Headline -->
                        <h1
                            class="hero-title animate-fade-up delay-1 text-white mb-3"
                        >
                            Your Home <br class="d-none d-lg-block" />
                            <span class="text-gold-italic">Away From Home</span>
                        </h1>

                        <!-- Subheadline -->
                        <p
                            class="hero-subtitle animate-fade-up delay-2 leading-relaxed mb-4"
                        >
                            Experience warm Filipino hospitality at EME's
                            Apartelle. Comfortable rooms, modern amenities, and
                            a prime location await you.
                        </p>

                        <!-- CTA Buttons -->
                        <div
                            class="hero-cta animate-fade-up delay-3 d-flex flex-wrap gap-3 gap-md-4 align-items-center"
                        >
                            <router-link
                                to="/rooms"
                                class="btn btn-gold btn-lg px-4 py-2.5 px-md-5 py-md-3 rounded-pill fw-bold text-uppercase tracking-wider shadow-gold hover-scale"
                            >
                                <i class="bi bi-calendar-check me-2"></i> Book
                                Your Stay
                            </router-link>
                            <div
                                class="location-badge d-flex align-items-center gap-2 text-white-50 border-start border-white-20 ps-3 ps-md-4"
                            >
                                <div
                                    class="icon-circle border border-white-20 rounded-circle p-2 text-gold"
                                >
                                    <i class="bi bi-geo-alt"></i>
                                </div>
                                <div class="d-flex flex-column">
                                    <span
                                        class="small text-gold fw-bold text-uppercase"
                                        style="font-size: 0.7rem; letter-spacing: 1px;"
                                        >Located at</span
                                    >
                                    <span class="text-white small"
                                        >General Luna, Philippines
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Content - Booking Card -->
                    <div class="col-lg-5 animate-fade-up delay-2">
                        <div
                            class="booking-card-premium glass-morph shadow-2xl position-relative"
                        >
                            <div class="p-4 p-md-4.5 p-xl-5">
                                <div class="mb-3.5 text-center">
                                    <span
                                        class="text-gold small fw-bold text-uppercase tracking-widest mb-1.5 d-block"
                                        style="font-size: 0.75rem;"
                                        >Book Form</span
                                    >
                                    <h3
                                        class="booking-card-title serif-font mb-2 fs-4 fw-bold"
                                    >
                                        Check Availability
                                    </h3>
                                    <div
                                        class="divider mx-auto bg-gold-subtle"
                                        style="width: 50px; height: 2px"
                                    ></div>
                                </div>

                                <form @submit.prevent="handleCheckAvailability">
                                    <div class="row g-3 mb-3.5">
                                        <div
                                            class="col-md-6 position-relative"
                                            ref="checkInWrapper"
                                        >
                                            <label class="form-label-premium"
                                                >Check-in</label
                                            >
                                            <div
                                                class="input-wrapper-premium cursor-pointer"
                                                @click="toggleCheckIn"
                                            >
                                                <i
                                                    class="bi bi-calendar-event input-icon"
                                                ></i>
                                                <div
                                                    class="form-control-premium d-flex align-items-center justify-content-between select-none"
                                                    :class="{
                                                        'border-gold shadow-gold-sm':
                                                            showCheckInCalendar,
                                                    }"
                                                >
                                                    <span
                                                        v-if="booking.checkIn"
                                                        class="fw-semibold text-secondary-dark"
                                                        >{{
                                                            formatDisplayDate(
                                                                booking.checkIn,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="text-muted opacity-75"
                                                        >Select date</span
                                                    >
                                                    <i
                                                        class="bi bi-chevron-down small text-muted opacity-50 ms-1"
                                                    ></i>
                                                </div>
                                            </div>
                                            <div
                                                v-if="showCheckInCalendar"
                                                class="calendar-popup position-absolute start-0 mt-2 animate-fade-in shadow-xl"
                                            >
                                                <CalendarPicker
                                                    v-model="booking.checkIn"
                                                    :min-date="today"
                                                    :disabled-dates="
                                                        blockedDates
                                                    "
                                                    @update:modelValue="
                                                        onCheckInSelected
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div
                                            class="col-md-6 position-relative"
                                            ref="checkOutWrapper"
                                        >
                                            <label class="form-label-premium"
                                                >Check-out</label
                                            >
                                            <div
                                                class="input-wrapper-premium cursor-pointer"
                                                @click="toggleCheckOut"
                                            >
                                                <i
                                                    class="bi bi-calendar-check input-icon"
                                                ></i>
                                                <div
                                                    class="form-control-premium d-flex align-items-center justify-content-between select-none"
                                                    :class="{
                                                        'border-gold shadow-gold-sm':
                                                            showCheckOutCalendar,
                                                    }"
                                                >
                                                    <span
                                                        v-if="booking.checkOut"
                                                        class="fw-semibold text-secondary-dark"
                                                        >{{
                                                            formatDisplayDate(
                                                                booking.checkOut,
                                                            )
                                                        }}</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="text-muted opacity-75"
                                                        >Select date</span
                                                    >
                                                    <i
                                                        class="bi bi-chevron-down small text-muted opacity-50 ms-1"
                                                    ></i>
                                                </div>
                                            </div>
                                            <div
                                                v-if="showCheckOutCalendar"
                                                class="calendar-popup position-absolute end-0 mt-2 animate-fade-in shadow-xl"
                                            >
                                                <CalendarPicker
                                                    v-model="booking.checkOut"
                                                    :min-date="
                                                        booking.checkIn || today
                                                    "
                                                    :max-date="maxCheckOutDate"
                                                    :disabled-dates="
                                                        blockedDates
                                                    "
                                                    :is-checkout="true"
                                                    @update:modelValue="
                                                        showCheckOutCalendar = false
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label-premium"
                                            >Total Guests</label
                                        >
                                        <GuestSelector
                                            v-model="booking.guests"
                                            v-model:adultsValue="booking.adults"
                                            v-model:childrenValue="
                                                booking.children
                                            "
                                            v-model:childrenAgesValue="
                                                booking.childrenAges
                                            "
                                            :maxOccupancy="6"
                                            :maxExtraGuests="4"
                                            triggerClass="form-control-premium w-100 px-3 py-2.5"
                                        />
                                    </div>

                                    <button
                                        type="submit"
                                        class="btn btn-gold-premium w-100 py-3 fw-bold text-uppercase tracking-widest shadow-gold d-flex align-items-center justify-content-center gap-2"
                                        :disabled="checking"
                                    >
                                        <span
                                            v-if="checking"
                                            class="spinner-border spinner-border-sm"
                                            role="status"
                                            aria-hidden="true"
                                        ></span>
                                        <span>{{
                                            checking
                                                ? "Checking..."
                                                : "Check Availability"
                                        }}</span>
                                        <i
                                            v-if="!checking"
                                            class="bi bi-arrow-right"
                                        ></i>
                                    </button>

                                    <p
                                        class="text-center text-muted small mt-4 pt-1 mb-0 opacity-75"
                                    >
                                        <i
                                            class="bi bi-shield-check text-success me-1"
                                        ></i>
                                        Best Rate Guaranteed
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== FEATURED ROOMS SECTION ========== -->
        <section class="rooms-section py-7 bg-cream position-relative">
            <div class="container">
                                                <!-- SECTION HEADER FOR PROMOS & PACKAGES -->
                <div
                    v-if="(promoRooms.length > 0 && activePromoRoom) || (packages.length > 0 && activePackage)"
                    class="section-header text-center mb-5 animate-fade-up"
                >
                    <div class="designer-label mx-auto mb-3">
                        <span class="label-dot"></span>
                        <span class="label-text">SPECIAL OFFERS</span>
                        <span class="label-dot"></span>
                    </div>
                    <h2
                        class="section-title display-4 serif-font text-secondary-dark"
                    >
                        {{
                            promoRooms.length > 0 && packages.length > 0
                                ? "Promos & Packages"
                                : promoRooms.length > 0
                                  ? "Special Promotions"
                                  : "Tour Packages"
                        }}
                    </h2>
                    <p
                        class="section-description mx-auto mt-3 text-muted"
                        style="max-width: 700px"
                    >
                        Discover our exclusive room discounts and all-inclusive tour packages for your stay.
                    </p>
                </div>

                <!-- PROMOTIONAL DEALS & PACKAGES SHOWCASE -->
                <!-- Case A: BOTH Promo Rooms and Packages are Active (Side-by-Side Cards) -->
                <div
                    v-if="(promoRooms.length > 0 && activePromoRoom) && (packages.length > 0 && activePackage)"
                    class="row g-4 mb-5 align-items-stretch"
                >
                    <!-- Left: Promotional Room Deal Card -->
                    <div class="col-lg-6 d-flex flex-column animate-fade-up">
                        <div
                            class="promo-clean-banner rounded-4 bg-white position-relative shadow-sm border overflow-hidden d-flex flex-column h-100 flex-grow-1"
                            @mouseenter="stopPromoTimer"
                            @mouseleave="startPromoTimer"
                        >
                            <div class="promo-gold-accent-line"></div>
                            <Transition name="promo-slide-fade" mode="out-in">
                                <div
                                    :key="activePromoRoom.id"
                                    class="promo-slide-container d-flex flex-column h-100 flex-grow-1"
                                >
                                    <!-- Photo Showcase with Badges -->
                                    <div class="position-relative overflow-hidden promo-card-photo-frame" style="height: 260px;">
                                        <img
                                            :src="getRoomImage(activePromoRoom)"
                                            :alt="activePromoRoom.room_type"
                                            class="w-100 h-100 object-fit-cover promo-clean-zoom"
                                            style="object-position: center;"
                                            @error="onImageError($event, activePromoRoom.room_type)"
                                        />
                                        <!-- Top Badges -->
                                        <div class="position-absolute top-0 start-0 end-0 p-3 d-flex justify-content-between align-items-center">
                                            <span class="promo-campaign-badge px-3 py-1.5 rounded-pill text-uppercase shadow-sm">
                                                <i class="bi bi-tag-fill me-1"></i>
                                                {{ activePromoRoom.promo_title || "Special Promotion" }}
                                            </span>
                                            <span
                                                v-if="activePromoRoom.promo_discount_percent"
                                                class="badge text-white fw-bold px-3 py-1.5 rounded-pill shadow-sm"
                                                style="font-size: 0.82rem; background: linear-gradient(135deg, #f43f5e 0%, #dc2626 100%) !important;"
                                            >
                                                {{ activePromoRoom.promo_discount_percent }}% OFF
                                            </span>
                                        </div>
                                        <!-- Bottom Caption -->
                                        <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-gradient-clean-caption d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="d-block serif-font text-white" style="font-size: 1.15rem; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                    Room #{{ activePromoRoom.room_number }} &mdash; {{ activePromoRoom.room_type }}
                                                </strong>
                                                <small style="color: #f8fafc !important; font-weight: 500; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                    EME's Apartelle &bull; General Luna
                                                </small>
                                            </div>
                                            <span class="badge bg-success text-white rounded-pill px-2.5 py-1 fw-bold shadow-sm" style="font-size: 0.75rem;">
                                                Available Now
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Card Content & Actions -->
                                    <div class="p-4 d-flex flex-column flex-grow-1 justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2 promo-title-row">
                                                <h3 class="serif-font fw-bold mb-0" style="font-size: 1.45rem; color: #0f172a !important;">
                                                    Room #{{ activePromoRoom.room_number }} &mdash; {{ activePromoRoom.room_type }}
                                                </h3>
                                                <div v-if="promoRooms.length > 1" class="d-flex align-items-center gap-1.5">
                                                    <span class="fw-bold px-1" style="font-size: 0.8rem; color: #475569;">
                                                        {{ activePromoIndex + 1 }} / {{ promoRooms.length }}
                                                    </span>
                                                    <button type="button" @click="prevPromo" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Previous Promo">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </button>
                                                    <button type="button" @click="nextPromo" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Next Promo">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <p class="mb-3 promo-deal-desc line-clamp-2">
                                                {{ activePromoRoom.promo_description || `Take advantage of our exclusive promotional rate for the ${activePromoRoom.room_type}. Enjoy premium amenities, comfortable accommodations, and guaranteed best rates.` }}
                                            </p>

                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 promo-perks-box">
                                                <span class="promo-perk-item"><i class="bi bi-people-fill text-gold"></i> Up to {{ activePromoRoom.max_occupancy }} Guests</span>
                                                <span v-if="activePromoRoom.bed_type" class="promo-perk-item"><i class="bi bi-door-closed-fill text-gold"></i> {{ activePromoRoom.bed_type }}</span>
                                                <span v-if="activePromoRoom.room_size" class="promo-perk-item"><i class="bi bi-arrows-fullscreen text-gold"></i> {{ activePromoRoom.room_size }} m²</span>
                                                <span class="promo-perk-item text-success fw-bold"><i class="bi bi-shield-check"></i> Free Cancellation</span>
                                                <template v-if="activePromoRoom.amenities && activePromoRoom.amenities.length > 0">
                                                    <span v-for="amenity in activePromoRoom.amenities.slice(0, 2)" :key="amenity.id" class="promo-perk-item">
                                                        <i :class="['bi', formatIconClass(amenity.icon) || 'bi-check-circle-fill', 'text-gold']"></i>
                                                        {{ amenity.name }}
                                                    </span>
                                                </template>
                                                <span v-else class="promo-perk-item"><i class="bi bi-wifi text-gold"></i> Free High-Speed WiFi</span>
                                            </div>
                                        </div>

                                        <div class="pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto">
                                            <div>
                                                <div class="d-flex align-items-baseline gap-1.5">
                                                    <span class="serif-font fw-bold mb-0 lh-1" style="font-size: 1.85rem; color: #bc9151 !important;">
                                                        ₱{{ formatPrice(getEffectiveRate(activePromoRoom)) }}
                                                    </span>
                                                    <span class="fw-semibold text-secondary small">
                                                        {{ isPerHead(activePromoRoom) ? "/ head / night" : "/ night" }}
                                                    </span>
                                                    <span class="text-decoration-line-through ms-1 text-muted small">
                                                        ₱{{ formatPrice(getRegularRate(activePromoRoom)) }}
                                                    </span>
                                                </div>
                                                <div class="mt-1 promo-badge-slot">
                                                    <span v-if="getPromoSavings(activePromoRoom) > 0" class="badge px-2.5 py-1 rounded-pill fw-bold" style="background-color: #ecfdf5 !important; color: #059669 !important; font-size: 0.78rem; border: 1px solid #a7f3d0;">
                                                        <i class="bi bi-piggy-bank-fill me-1"></i>
                                                        Save ₱{{ formatPrice(getPromoSavings(activePromoRoom)) }} ({{ activePromoRoom.promo_discount_percent }}% OFF)
                                                    </span>
                                                </div>
                                            </div>
                                            <router-link :to="`/rooms/${activePromoRoom.id}`" class="btn btn-promo-deal px-4 py-2.5 rounded-pill fw-bold text-uppercase small d-inline-flex align-items-center gap-2 text-decoration-none shadow-sm">
                                                <span>Book This Deal</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </router-link>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>

                    <!-- Right: Tour & Stay Package Deal Card -->
                    <div class="col-lg-6 d-flex flex-column animate-fade-up">
                        <div
                            class="package-clean-banner rounded-4 bg-white position-relative shadow-sm border overflow-hidden d-flex flex-column h-100 flex-grow-1"
                            @mouseenter="stopPackageTimer"
                            @mouseleave="startPackageTimer"
                        >
                            <div class="package-teal-accent-line"></div>
                            <Transition name="promo-slide-fade" mode="out-in">
                                <div :key="activePackage.id" class="promo-slide-container d-flex flex-column h-100 flex-grow-1">
                                    <div class="position-relative overflow-hidden promo-card-photo-frame" style="height: 260px;">
                                        <img :src="getPackageImage(activePackage)" :alt="activePackage.name" class="w-100 h-100 object-fit-cover promo-clean-zoom" style="object-position: center;" />
                                        <div class="position-absolute top-0 start-0 end-0 p-3 d-flex justify-content-between align-items-center">
                                            <span class="package-campaign-badge px-3 py-1.5 rounded-pill text-uppercase shadow-sm">
                                                <i class="bi bi-box2-heart-fill me-1"></i>
                                                Tour &amp; Stay Package
                                            </span>
                                            <span class="badge text-white fw-bold px-3 py-1.5 rounded-pill shadow-sm" style="font-size: 0.82rem; background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;">
                                                <i class="bi bi-clock-history me-1"></i>
                                                {{ activePackage.duration || '3 Days, 2 Nights' }}
                                            </span>
                                        </div>
                                        <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-gradient-clean-caption d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="d-block serif-font text-white" style="font-size: 1.15rem; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                    {{ activePackage.name }}
                                                </strong>
                                                <small style="color: #f8fafc !important; font-weight: 500; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                    <i class="bi bi-geo-alt-fill text-warning me-1"></i>
                                                    {{ activePackage.tagline || 'Accommodation + Island Hopping' }}
                                                </small>
                                            </div>
                                            <span class="badge bg-info text-white rounded-pill px-2.5 py-1 fw-bold shadow-sm" style="font-size: 0.75rem;">
                                                {{ activePackage.pax || 'Good for 2 pax' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-4 d-flex flex-column flex-grow-1 justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2 promo-title-row">
                                                <h3 class="serif-font fw-bold mb-0 text-truncate pe-2" style="font-size: 1.45rem; color: #0f172a !important;" :title="activePackage.name">
                                                    {{ activePackage.name }}
                                                </h3>
                                                <div v-if="packages.length > 1" class="d-flex align-items-center gap-1.5 flex-shrink-0">
                                                    <span class="fw-bold px-1" style="font-size: 0.8rem; color: #475569;">
                                                        {{ activePackageIndex + 1 }} / {{ packages.length }}
                                                    </span>
                                                    <button type="button" @click="prevPackage" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Previous Package">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </button>
                                                    <button type="button" @click="nextPackage" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Next Package">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <p class="mb-3 promo-deal-desc line-clamp-2">
                                                {{ activePackage.description || (activePackage.tagline ? `${activePackage.tagline}. Inclusive of comfortable accommodation and island tour.` : 'Experience Siargao with comfortable private room accommodation paired with an unforgettable island hopping adventure.') }}
                                            </p>

                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 promo-perks-box">
                                                <span class="promo-perk-item"><i class="bi bi-people-fill text-teal"></i> {{ activePackage.pax || 'Good for 2 pax' }}</span>
                                                <span class="promo-perk-item"><i class="bi bi-compass-fill text-info"></i> {{ activePackage.tour_type || 'Joiners Tour' }}</span>
                                                <span v-if="activePackage.room" class="promo-perk-item"><i class="bi bi-door-closed-fill text-teal"></i> Room #{{ activePackage.room.room_number }} ({{ activePackage.room.room_type }})</span>
                                                <span v-for="dest in (activePackage.tour_destinations || []).slice(0, 2)" :key="dest" class="promo-perk-item">🏝️ {{ dest }}</span>
                                                <span v-if="(activePackage.tour_inclusions || []).length > 0" class="promo-perk-item text-success fw-bold">🍱 Lunch Included</span>
                                            </div>
                                        </div>

                                        <div class="pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto">
                                            <div>
                                                <div class="d-flex align-items-baseline gap-1.5">
                                                    <span class="serif-font fw-bold mb-0 lh-1" style="font-size: 1.85rem; color: #0d9488 !important;">
                                                        ₱{{ formatPrice(activePackage.price) }}
                                                    </span>
                                                    <span class="fw-semibold text-secondary small">/ all-inclusive</span>
                                                </div>
                                                <div class="mt-1 promo-badge-slot">
                                                    <span class="badge px-2.5 py-1 rounded-pill fw-bold" style="background-color: #f0fdfa !important; color: #0f766e !important; font-size: 0.78rem; border: 1px solid #99f6e4;">
                                                        <i class="bi bi-shield-check me-1"></i>
                                                        Room + Island Hopping Tour
                                                    </span>
                                                </div>
                                            </div>
                                            <button type="button" @click="openGuestPackageModal(activePackage)" class="btn btn-package-deal px-4 py-2.5 rounded-pill fw-bold text-uppercase small d-inline-flex align-items-center gap-2 border-0 shadow-sm">
                                                <span>View Package</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- Case B: ONLY Promo Rooms are Active (Full-Width Horizontal Split - Image on Right) -->
                <div
                    v-else-if="promoRooms.length > 0 && activePromoRoom"
                    class="row mb-5 animate-fade-up"
                >
                    <div class="col-12">
                        <div
                            class="promo-clean-banner rounded-4 bg-white position-relative shadow-sm border overflow-hidden"
                            @mouseenter="stopPromoTimer"
                            @mouseleave="startPromoTimer"
                        >
                            <div class="promo-gold-accent-line"></div>
                            <Transition name="promo-slide-fade" mode="out-in">
                                <div :key="activePromoRoom.id" class="row g-0 align-items-stretch">
                                    <!-- Left: Clean Info & Action -->
                                    <div class="col-lg-7 p-4 p-md-5 d-flex flex-column justify-content-between">
                                        <div>
                                            <!-- Header: Badge & Controls -->
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="promo-campaign-badge px-3 py-1.5 rounded-pill text-uppercase shadow-sm">
                                                        <i class="bi bi-tag-fill me-1"></i>
                                                        {{ activePromoRoom.promo_title || "Special Promotion" }}
                                                    </span>
                                                    <span
                                                        v-if="activePromoRoom.promo_discount_percent"
                                                        class="badge text-white fw-bold px-3 py-1.5 rounded-pill shadow-sm"
                                                        style="font-size: 0.82rem; background: linear-gradient(135deg, #f43f5e 0%, #dc2626 100%) !important;"
                                                    >
                                                        {{ activePromoRoom.promo_discount_percent }}% OFF
                                                    </span>
                                                </div>

                                                <div v-if="promoRooms.length > 1" class="d-flex align-items-center gap-1.5">
                                                    <span class="fw-bold px-1" style="font-size: 0.82rem; color: #475569;">
                                                        {{ activePromoIndex + 1 }} / {{ promoRooms.length }}
                                                    </span>
                                                    <button type="button" @click="prevPromo" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Previous Promo">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </button>
                                                    <button type="button" @click="nextPromo" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Next Promo">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Headline -->
                                            <h2 class="serif-font fw-bold mb-2" style="font-size: 2rem; color: #0f172a !important;">
                                                Room #{{ activePromoRoom.room_number }} &mdash; {{ activePromoRoom.room_type }}
                                            </h2>

                                            <!-- Description -->
                                            <p class="mb-3" style="font-size: 0.95rem; line-height: 1.6; color: #475569 !important; max-width: 560px;">
                                                {{ activePromoRoom.promo_description || `Take advantage of our exclusive promotional rate for the ${activePromoRoom.room_type}. Enjoy premium amenities, comfortable accommodations, and guaranteed best rates.` }}
                                            </p>

                                            <!-- Perks -->
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                                                <span class="promo-perk-item">
                                                    <i class="bi bi-people-fill text-gold"></i>
                                                    Up to {{ activePromoRoom.max_occupancy }} Guests
                                                </span>
                                                <span v-if="activePromoRoom.bed_type" class="promo-perk-item">
                                                    <i class="bi bi-door-closed-fill text-gold"></i>
                                                    {{ activePromoRoom.bed_type }}
                                                </span>
                                                <span v-if="activePromoRoom.room_size" class="promo-perk-item">
                                                    <i class="bi bi-arrows-fullscreen text-gold"></i>
                                                    {{ activePromoRoom.room_size }} m²
                                                </span>
                                                <span class="promo-perk-item text-success fw-bold">
                                                    <i class="bi bi-shield-check"></i>
                                                    Free Cancellation
                                                </span>
                                                <template v-if="activePromoRoom.amenities && activePromoRoom.amenities.length > 0">
                                                    <span v-for="amenity in activePromoRoom.amenities.slice(0, 2)" :key="amenity.id" class="promo-perk-item">
                                                        <i :class="['bi', formatIconClass(amenity.icon) || 'bi-check-circle-fill', 'text-gold']"></i>
                                                        {{ amenity.name }}
                                                    </span>
                                                </template>
                                                <span v-else class="promo-perk-item">
                                                    <i class="bi bi-wifi text-gold"></i>
                                                    Free High-Speed WiFi
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Price & Booking CTA -->
                                        <div class="pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3 mt-auto">
                                            <div>
                                                <div class="d-flex align-items-baseline gap-2">
                                                    <span class="serif-font fw-bold mb-0 lh-1" style="font-size: 2.25rem; color: #bc9151 !important;">
                                                        ₱{{ formatPrice(getEffectiveRate(activePromoRoom)) }}
                                                    </span>
                                                    <span class="fw-semibold text-secondary" style="font-size: 0.9rem;">
                                                        {{ isPerHead(activePromoRoom) ? "/ head / night" : "/ night" }}
                                                    </span>
                                                    <span class="text-decoration-line-through ms-2 fw-normal" style="color: #94a3b8; font-size: 1.05rem;">
                                                        ₱{{ formatPrice(getRegularRate(activePromoRoom)) }}
                                                    </span>
                                                </div>
                                                <div v-if="getPromoSavings(activePromoRoom) > 0" class="mt-1.5">
                                                    <span class="badge px-3 py-1.5 rounded-pill fw-bold shadow-xs" style="background-color: #ecfdf5 !important; color: #059669 !important; font-size: 0.82rem; border: 1.5px solid #a7f3d0;">
                                                        <i class="bi bi-piggy-bank-fill me-1"></i>
                                                        Save ₱{{ formatPrice(getPromoSavings(activePromoRoom)) }} ({{ activePromoRoom.promo_discount_percent }}% OFF)
                                                    </span>
                                                </div>
                                            </div>

                                            <router-link
                                                :to="`/rooms/${activePromoRoom.id}`"
                                                class="btn btn-promo-deal px-4 py-2.5 rounded-pill fw-bold text-uppercase small d-inline-flex align-items-center gap-2 text-decoration-none shadow-sm"
                                            >
                                                <span>Book This Deal</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </router-link>
                                        </div>
                                    </div>

                                    <!-- Right: Clean Full Display Photo Frame -->
                                    <div class="col-lg-5 p-3 p-md-4 d-flex">
                                        <div class="promo-clean-photo-frame position-relative rounded-4 overflow-hidden shadow-sm w-100" style="min-height: 380px;">
                                            <img
                                                :src="getRoomImage(activePromoRoom)"
                                                :alt="activePromoRoom.room_type"
                                                class="w-100 h-100 object-fit-cover promo-clean-zoom"
                                                @error="onImageError($event, activePromoRoom.room_type)"
                                            />
                                            <span
                                                v-if="activePromoRoom.promo_discount_percent"
                                                class="badge bg-danger text-white position-absolute top-0 end-0 m-3 px-3 py-1.5 rounded-pill shadow-sm fw-bold"
                                                style="font-size: 0.85rem;"
                                            >
                                                {{ activePromoRoom.promo_discount_percent }}% OFF
                                            </span>

                                            <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-gradient-clean-caption d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong class="d-block serif-font text-white" style="font-size: 1.15rem; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                        Room #{{ activePromoRoom.room_number }}
                                                    </strong>
                                                    <small style="color: #f8fafc !important; font-weight: 500; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                        {{ activePromoRoom.room_type }} &bull; EME's Apartelle
                                                    </small>
                                                </div>
                                                <span class="badge bg-success text-white rounded-pill px-2.5 py-1 fw-bold shadow-sm" style="font-size: 0.75rem;">
                                                    Available Now
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- Case C: ONLY Packages are Active (Full-Width Horizontal Split - Image on Right) -->
                <div
                    v-else-if="packages.length > 0 && activePackage"
                    class="row mb-5 animate-fade-up"
                >
                    <div class="col-12">
                        <div
                            class="package-clean-banner rounded-4 bg-white position-relative shadow-sm border overflow-hidden"
                            @mouseenter="stopPackageTimer"
                            @mouseleave="startPackageTimer"
                        >
                            <div class="package-teal-accent-line"></div>
                            <Transition name="promo-slide-fade" mode="out-in">
                                <div :key="activePackage.id" class="row g-0 align-items-stretch">
                                    <!-- Left: Package Details & Booking CTA -->
                                    <div class="col-lg-7 p-4 p-md-5 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="package-campaign-badge px-3 py-1.5 rounded-pill text-uppercase shadow-sm">
                                                        <i class="bi bi-box2-heart-fill me-1"></i>
                                                        Tour &amp; Stay Package
                                                    </span>
                                                    <span class="badge text-white fw-bold px-3 py-1.5 rounded-pill shadow-sm" style="font-size: 0.82rem; background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;">
                                                        <i class="bi bi-clock-history me-1"></i>
                                                        {{ activePackage.duration || '3 Days, 2 Nights' }}
                                                    </span>
                                                </div>

                                                <div v-if="packages.length > 1" class="d-flex align-items-center gap-1.5">
                                                    <span class="fw-bold px-1" style="font-size: 0.82rem; color: #475569;">
                                                        {{ activePackageIndex + 1 }} / {{ packages.length }}
                                                    </span>
                                                    <button type="button" @click="prevPackage" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Previous Package">
                                                        <i class="bi bi-chevron-left"></i>
                                                    </button>
                                                    <button type="button" @click="nextPackage" class="btn btn-sm btn-light border rounded-circle p-0 promo-arrow-btn" title="Next Package">
                                                        <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <h2 class="serif-font fw-bold mb-2" style="font-size: 2rem; color: #0f172a !important;">
                                                {{ activePackage.name }}
                                            </h2>

                                            <p class="mb-3" style="font-size: 0.95rem; line-height: 1.6; color: #475569 !important; max-width: 560px;">
                                                {{ activePackage.description || activePackage.tagline || 'Experience Siargao with comfortable private room accommodation paired with an unforgettable island hopping adventure.' }}
                                            </p>

                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                                                <span class="promo-perk-item"><i class="bi bi-people-fill text-teal"></i> {{ activePackage.pax || 'Good for 2 pax' }}</span>
                                                <span class="promo-perk-item"><i class="bi bi-compass-fill text-info"></i> {{ activePackage.tour_type || 'Joiners Tour' }}</span>
                                                <span v-if="activePackage.room" class="promo-perk-item"><i class="bi bi-door-closed-fill text-teal"></i> Room #{{ activePackage.room.room_number }} ({{ activePackage.room.room_type }})</span>
                                                <span v-for="dest in (activePackage.tour_destinations || []).slice(0, 3)" :key="dest" class="promo-perk-item">🏝️ {{ dest }}</span>
                                                <span v-if="(activePackage.tour_inclusions || []).length > 0" class="promo-perk-item text-success fw-bold">🍱 Lunch Included</span>
                                            </div>
                                        </div>

                                        <div class="pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-3 mt-auto">
                                            <div>
                                                <div class="d-flex align-items-baseline gap-2">
                                                    <span class="serif-font fw-bold mb-0 lh-1" style="font-size: 2.25rem; color: #0d9488 !important;">
                                                        ₱{{ formatPrice(activePackage.price) }}
                                                    </span>
                                                    <span class="fw-semibold text-secondary" style="font-size: 0.9rem;">/ all-inclusive</span>
                                                </div>
                                                <div class="mt-1.5">
                                                    <span class="badge px-3 py-1.5 rounded-pill fw-bold" style="background-color: #f0fdfa !important; color: #0f766e !important; font-size: 0.82rem; border: 1.5px solid #99f6e4;">
                                                        <i class="bi bi-shield-check me-1"></i>
                                                        Room + Island Hopping Tour
                                                    </span>
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                @click="openGuestPackageModal(activePackage)"
                                                class="btn btn-package-deal px-4 py-2.5 rounded-pill fw-bold text-uppercase small d-inline-flex align-items-center gap-2 border-0 shadow-sm"
                                            >
                                                <span>View Package</span>
                                                <i class="bi bi-arrow-right"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Right: Clean Full Display Package Image Frame -->
                                    <div class="col-lg-5 p-3 p-md-4 d-flex">
                                        <div class="promo-clean-photo-frame position-relative rounded-4 overflow-hidden shadow-sm w-100" style="min-height: 380px;">
                                            <img :src="getPackageImage(activePackage)" :alt="activePackage.name" class="w-100 h-100 object-fit-cover promo-clean-zoom" />
                                            <div class="position-absolute bottom-0 start-0 end-0 p-3 bg-gradient-clean-caption d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong class="d-block serif-font text-white" style="font-size: 1.15rem; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                        {{ activePackage.name }}
                                                    </strong>
                                                    <small style="color: #f8fafc !important; font-weight: 500; text-shadow: 0 1px 4px rgba(0, 0, 0, 0.9);">
                                                        <i class="bi bi-geo-alt-fill text-warning me-1"></i>
                                                        {{ activePackage.tagline || 'Accommodation + Island Hopping' }}
                                                    </small>
                                                </div>
                                                <span class="badge bg-info text-white rounded-pill px-2.5 py-1 fw-bold shadow-sm" style="font-size: 0.75rem;">
                                                    {{ activePackage.pax || 'Good for 2 pax' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <div
                    class="section-header text-center mb-5 pt-2 animate-fade-up"
                >
                    <div class="designer-label mx-auto mb-3">
                        <span class="label-dot"></span>
                        <span class="label-text">EME'S APARTELLE</span>
                        <span class="label-dot"></span>
                    </div>
                    <h2
                        class="section-title display-4 serif-font text-secondary-dark"
                    >
                        Available Rooms
                    </h2>
                    <p
                        class="section-description mx-auto mt-3 text-muted"
                        style="max-width: 700px"
                    >
                        Check out our clean and comfortable rooms for your stay.
                    </p>
                </div>

                <div v-if="loadingRooms" class="row g-4 justify-content-center">
                    <div
                        v-for="n in 3"
                        :key="'skel-' + n"
                        class="col-lg-4 col-md-6"
                    >
                        <div
                            class="room-card bg-white rounded-4 overflow-hidden border-0 shadow-sm"
                        >
                            <div
                                class="skeleton-image skeleton-shimmer"
                                style="height: 280px"
                            ></div>
                            <div class="p-4">
                                <div
                                    class="skeleton-line skeleton-shimmer mb-2"
                                    style="width: 45%; height: 10px"
                                ></div>
                                <div
                                    class="skeleton-line skeleton-shimmer mb-3"
                                    style="width: 60%; height: 20px"
                                ></div>
                                <div class="d-flex gap-3 mb-4">
                                    <div
                                        class="skeleton-line skeleton-shimmer"
                                        style="width: 70px; height: 14px"
                                    ></div>
                                    <div
                                        class="skeleton-line skeleton-shimmer"
                                        style="width: 50px; height: 14px"
                                    ></div>
                                </div>
                                <div
                                    class="skeleton-btn skeleton-shimmer"
                                    style="
                                        width: 100%;
                                        height: 38px;
                                        border-radius: 50px;
                                    "
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="row g-4 justify-content-center">
                    <div
                        v-for="(room, index) in rooms"
                        :key="room.id"
                        class="col-lg-4 col-md-6 animate-fade-up"
                        :style="{ animationDelay: index * 100 + 'ms' }"
                    >
                        <div
                            class="room-card card-hover shadow-gold-sm border-0 bg-white rounded-4 overflow-hidden d-flex flex-column h-100 position-relative"
                        >
                            <div class="room-card-image position-relative">
                                <img
                                    :src="getRoomImage(room)"
                                    :alt="room.room_type"
                                    loading="lazy"
                                    @error="
                                        onImageError($event, room.room_type)
                                    "
                                />
                                <span
                                    class="room-badge"
                                    :class="
                                        room.status === 'available'
                                            ? 'badge-available'
                                            : 'badge-unavailable'
                                    "
                                >
                                    {{
                                        room.status === "available"
                                            ? "Available"
                                            : "Occupied"
                                    }}
                                </span>
                                <span
                                    v-if="room.is_promo"
                                    class="room-promo-badge badge bg-danger text-white position-absolute top-0 end-0 m-3 px-2.5 py-1.5 rounded-pill shadow-sm fw-bold z-2"
                                >
                                    <i class="bi bi-tag-fill me-1"></i>
                                    {{
                                        room.promo_discount_percent
                                            ? `${room.promo_discount_percent}% OFF`
                                            : "PROMO"
                                    }}
                                </span>
                                <div
                                    class="room-size-tag"
                                    v-if="room.room_size"
                                >
                                    <i
                                        class="bi bi-arrows-fullscreen small me-1"
                                    ></i>
                                    {{ room.room_size }} m²
                                </div>
                            </div>
                            <div
                                class="room-card-body p-4 d-flex flex-column flex-grow-1"
                            >
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2"
                                >
                                    <span
                                        class="text-gold small fw-bold text-uppercase tracking-widest"
                                        >{{ room.room_type }}</span
                                    >
                                    <div
                                        class="room-bed-type small text-muted"
                                        v-if="room.bed_type"
                                    >
                                        <i class="bi bi-door-open me-1"></i
                                        >{{ room.bed_type }}
                                    </div>
                                </div>
                                <h4 class="room-card-title serif-font mb-2">
                                    Room #{{ room.room_number }}
                                </h4>

                                <div class="room-card-price mb-3">
                                    <template v-if="room.is_promo">
                                        <span class="currency text-gold"
                                            >₱</span
                                        >
                                        <span class="price text-gold">
                                            {{
                                                formatPrice(
                                                    getEffectiveRate(room),
                                                )
                                            }}
                                        </span>
                                        <span class="period">
                                            {{
                                                isPerHead(room)
                                                    ? "/ head / night"
                                                    : "/ night"
                                            }}
                                        </span>
                                        <span
                                            class="text-muted text-decoration-line-through ms-2 small"
                                        >
                                            ₱{{
                                                formatPrice(
                                                    getRegularRate(room),
                                                )
                                            }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="currency">₱</span>
                                        <span class="price">
                                            {{
                                                formatPrice(
                                                    isPerHead(room)
                                                        ? room.price_per_head
                                                        : room.price_per_night,
                                                )
                                            }}
                                        </span>
                                        <span class="period">
                                            {{
                                                isPerHead(room)
                                                    ? "/ head / night"
                                                    : "/ night"
                                            }}
                                        </span>
                                    </template>
                                </div>

                                <div class="room-features mb-4">
                                    <div class="feature-pill" title="Occupancy">
                                        <i
                                            class="bi bi-people-fill text-gold"
                                        ></i>
                                        <span
                                            v-if="
                                                room.min_occupancy &&
                                                room.min_occupancy > 1
                                            "
                                            >{{ room.min_occupancy }} to
                                            {{
                                                room.max_occupancy
                                            }}
                                            Guests</span
                                        >
                                        <span v-else
                                            >{{
                                                room.max_occupancy
                                            }}
                                            Guests</span
                                        >
                                    </div>
                                    <div
                                        class="feature-pill"
                                        v-for="amenity in room.amenities
                                            ? room.amenities.slice(0, 2)
                                            : []"
                                        :key="amenity.id"
                                        :title="amenity.name"
                                    >
                                        <i
                                            :class="[
                                                'bi',
                                                formatIconClass(amenity.icon),
                                            ]"
                                            class="text-gold"
                                        ></i>
                                        <span>{{ amenity.name }}</span>
                                    </div>
                                    <div
                                        class="feature-pill"
                                        v-if="
                                            room.amenities &&
                                            room.amenities.length > 2
                                        "
                                        :title="
                                            room.amenities
                                                .slice(2)
                                                .map((a) => a.name)
                                                .join(', ')
                                        "
                                    >
                                        <span
                                            >+{{
                                                room.amenities.length - 2
                                            }}
                                            more</span
                                        >
                                    </div>
                                </div>

                                <router-link
                                    :to="`/rooms/${room.id}`"
                                    class="btn btn-gold-outline w-100 py-2 rounded-pill fw-bold text-uppercase small mt-auto text-center text-decoration-none"
                                >
                                    View Room
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5" v-if="rooms.length > 0">
                    <router-link
                        to="/rooms"
                        class="btn btn-outline-dark-custom px-5 py-3 rounded-pill fw-bold text-uppercase tracking-wider small"
                    >
                        View All Rooms
                    </router-link>
                </div>
            </div>
        </section>

        <!-- ========== AMENITIES SECTION ========== -->
        <section class="amenities-section py-7 bg-white">
            <div class="container">
                <div class="section-header text-center mb-5 animate-fade-up">
                    <div class="designer-label mx-auto mb-3">
                        <span class="label-dot"></span>
                        <span class="label-text">AMENITIES</span>
                        <span class="label-dot"></span>
                    </div>
                    <h2
                        class="section-title display-4 serif-font text-secondary-dark"
                    >
                        What We Offer
                    </h2>
                    <p
                        class="section-description mx-auto mt-3 text-muted"
                        style="max-width: 650px"
                    >
                        We have everything you need for a good stay.
                    </p>
                </div>

                <div class="row g-4">
                    <div
                        v-for="(amenity, index) in displayedAmenities"
                        :key="index"
                        class="col-lg-3 col-md-6 animate-fade-up"
                        :style="{ animationDelay: index * 50 + 'ms' }"
                    >
                        <div
                            class="amenity-card-premium hover-lift text-center p-4 rounded-4 bg-light border-0"
                        >
                            <div
                                class="amenity-icon icon-box-gold mx-auto mb-3"
                            >
                                <i
                                    :class="[
                                        'bi',
                                        formatIconClass(amenity.icon),
                                    ]"
                                ></i>
                            </div>
                            <h5 class="amenity-title fw-bold">
                                {{ amenity.name }}
                            </h5>
                            <p class="amenity-description small opacity-75">
                                {{ amenity.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Show More / Show Less Toggle Button -->
                <div
                    v-if="amenities && amenities.length > 8"
                    class="text-center mt-5 animate-fade-up"
                >
                    <button
                        type="button"
                        class="btn btn-outline-gold px-4 py-2.5 rounded-pill fw-bold text-uppercase tracking-wider small d-inline-flex align-items-center gap-2 shadow-sm"
                        @click="toggleAmenities"
                    >
                        <span>{{ showAllAmenities ? 'Show Less' : 'Show More' }}</span>
                        <i
                            class="bi"
                            :class="showAllAmenities ? 'bi-chevron-up' : 'bi-chevron-down'"
                        ></i>
                    </button>
                </div>
            </div>
        </section>

        <!-- ========== TESTIMONIALS SECTION ========== -->
        <section class="testimonials-section">
            <div class="container">
                <div class="section-header text-center animate-fade-up">
                    <span class="section-label text-primary-gold"
                        >Testimonials</span
                    >
                    <h2 class="section-title display-4 text-white serif-font">
                        What Our Guests Say
                    </h2>
                    <p class="section-description text-white-50">
                        Don't just take our word for it. Here's what our guests
                        have to say about their stay.
                    </p>
                </div>

                <div class="row g-4">
                    <div
                        v-for="(testimonial, index) in testimonials"
                        :key="index"
                        class="col-md-4 animate-fade-up"
                        :style="{ animationDelay: index * 100 + 'ms' }"
                    >
                        <div
                            class="testimonial-card-premium hover-lift glass-panel p-4 rounded-4"
                        >
                            <div class="quote-icon text-gold fs-1 mb-2">"</div>
                            <p
                                class="testimonial-text text-white-50 font-italic small mb-4 line-clamp-4"
                            >
                                "{{ testimonial.text }}"
                            </p>
                            <div
                                class="testimonial-author d-flex align-items-center gap-3"
                            >
                                <img
                                    :src="testimonial.image"
                                    :alt="testimonial.name"
                                    class="testimonial-avatar rounded-circle border border-2 border-gold"
                                    width="50"
                                    height="50"
                                    loading="lazy"
                                />
                                <div>
                                    <h6
                                        class="testimonial-name text-white mb-0 fw-bold"
                                    >
                                        {{ testimonial.name }}
                                    </h6>
                                    <span
                                        class="testimonial-role text-gold small"
                                        >{{ testimonial.role }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== CTA SECTION ========== -->
        <section
            class="cta-section py-7 bg-white position-relative overflow-hidden"
        >
            <!-- Decorative background blur -->
            <div class="cta-blur-blob"></div>

            <div class="container position-relative z-1">
                <div class="cta-content text-center animate-fade-up">
                    <div
                        class="designer-label mx-auto mb-4 justify-content-center"
                    >
                        <span class="label-dot"></span>
                        <span class="label-text">YOUR JOURNEY AWAITS</span>
                        <span class="label-dot"></span>
                    </div>
                    <h2
                        class="cta-title display-3 serif-font text-secondary-dark mb-4"
                    >
                        Ready to book your
                        <span class="text-gold-italic">stay?</span>
                    </h2>
                    <p
                        class="cta-description mx-auto mb-5 text-muted"
                        style="max-width: 600px"
                    >
                        Contact us or book online to reserve your room today.
                    </p>
                    <div
                        class="cta-buttons d-flex flex-wrap justify-content-center gap-4"
                    >
                        <router-link
                            to="/rooms"
                            class="btn btn-gold btn-lg px-5 py-3 rounded-pill fw-bold text-uppercase tracking-wider shadow-gold hover-scale"
                        >
                            Check Availability
                        </router-link>
                        <a
                            href="tel:+639123456789"
                            class="btn btn-outline-dark-custom btn-lg px-5 py-3 rounded-pill fw-bold text-uppercase tracking-wider small"
                        >
                            Call Us
                        </a>
                    </div>
                    <div
                        class="cta-features mt-5 d-flex flex-wrap justify-content-center gap-lg-5 gap-3 text-muted small fw-bold"
                    >
                        <span
                            ><i class="bi bi-patch-check text-success me-2"></i
                            >Instant Confirmation</span
                        >
                        <span
                            ><i class="bi bi-patch-check text-success me-2"></i
                            >Secure Payment</span
                        >
                        <span
                            ><i class="bi bi-patch-check text-success me-2"></i
                            >Zero Hidden Fees</span
                        >
                    </div>
                </div>
            </div>
        </section>
        <!-- ========== AVAILABLE ROOMS MODAL ========== -->
        <Teleport to="body">
            <div
                v-if="showRoomsModal"
                class="modal-backdrop fade show"
                style="z-index: 1050"
                @click="closeRoomsModal"
            ></div>
            <div
                v-if="showRoomsModal"
                class="modal fade show d-block"
                tabindex="-1"
                style="z-index: 1055"
                @click.self="closeRoomsModal"
            >
                <div
                    class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"
                >
                    <div
                        class="modal-content border-0 rounded-4 overflow-hidden shadow-2xl"
                    >
                        <!-- Modal Header -->
                        <div
                            class="modal-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center"
                        >
                            <div>
                                <h3
                                    class="modal-title serif-font text-secondary-dark mb-1"
                                >
                                    Available Accommodations
                                </h3>
                                <p
                                    class="text-muted small mb-0 d-flex flex-wrap align-items-center gap-2"
                                >
                                    <span
                                        ><i
                                            class="bi bi-calendar-check text-gold"
                                        ></i>
                                        {{
                                            formatDisplayDate(booking.checkIn)
                                        }}
                                        to
                                        {{
                                            formatDisplayDate(booking.checkOut)
                                        }}</span
                                    >
                                    <span class="bullet-divider">•</span>
                                    <span
                                        ><i class="bi bi-moon text-gold"></i>
                                        {{ totalNights }}
                                        {{
                                            totalNights === 1
                                                ? "night"
                                                : "nights"
                                        }}</span
                                    >
                                    <span class="bullet-divider">•</span>
                                    <span>
                                        <i class="bi bi-people text-gold"></i>
                                        {{
                                            booking.adults || booking.guests
                                        }}
                                        Adult{{
                                            (booking.adults || booking.guests) >
                                            1
                                                ? "s"
                                                : ""
                                        }}
                                        <template v-if="booking.children > 0">
                                            , {{ booking.children }} Child{{
                                                booking.children > 1
                                                    ? "ren"
                                                    : ""
                                            }}
                                        </template>
                                    </span>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="btn-close bg-light rounded-circle p-2"
                                @click="closeRoomsModal"
                                aria-label="Close"
                            ></button>
                        </div>

                        <!-- Modal Body -->
                        <div class="modal-body bg-cream p-4">
                            <div class="row g-4 justify-content-center">
                                <div
                                    v-for="(room, index) in availableRooms"
                                    :key="room.id"
                                    class="col-12 animate-fade-up"
                                    :style="{
                                        animationDelay: index * 50 + 'ms',
                                    }"
                                >
                                    <div
                                        class="room-horizontal-card bg-white rounded-4 shadow-sm overflow-hidden border-0 d-flex flex-column flex-md-row"
                                    >
                                        <!-- Image Area -->
                                        <div
                                            class="room-card-image position-relative flex-shrink-0"
                                        >
                                            <img
                                                :src="getRoomImage(room)"
                                                :alt="room.room_type"
                                                loading="lazy"
                                                class="w-100 h-100 object-fit-cover"
                                                @error="
                                                    onImageError(
                                                        $event,
                                                        room.room_type,
                                                    )
                                                "
                                            />
                                            <span
                                                class="room-badge badge-available"
                                            >
                                                Available
                                            </span>
                                            <span
                                                v-if="room.is_promo"
                                                class="badge bg-danger text-white position-absolute top-0 end-0 m-3 px-2.5 py-1 rounded-pill shadow-sm fw-bold x-small z-2"
                                            >
                                                <i
                                                    class="bi bi-tag-fill me-1"
                                                ></i>
                                                {{
                                                    room.promo_discount_percent
                                                        ? `${room.promo_discount_percent}% OFF`
                                                        : "PROMO"
                                                }}
                                            </span>
                                            <div
                                                class="room-size-tag"
                                                v-if="room.room_size"
                                            >
                                                <i
                                                    class="bi bi-arrows-fullscreen small me-1"
                                                ></i
                                                >{{ room.room_size }} m²
                                            </div>
                                        </div>

                                        <!-- Content Area -->
                                        <div
                                            class="room-card-body p-4 d-flex flex-column flex-grow-1 justify-content-between"
                                        >
                                            <div>
                                                <div
                                                    class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2"
                                                >
                                                    <span
                                                        class="text-gold small fw-bold text-uppercase tracking-widest"
                                                        >{{
                                                            room.room_type
                                                        }}</span
                                                    >
                                                    <span
                                                        class="feature-badge"
                                                        v-if="room.bed_type"
                                                    >
                                                        <i
                                                            class="bi bi-door-open me-1"
                                                        ></i
                                                        >{{ room.bed_type }}
                                                    </span>
                                                </div>
                                                <h4
                                                    class="room-card-title serif-font mb-2"
                                                >
                                                    Room #{{ room.room_number }}
                                                </h4>

                                                <!-- Features Row -->
                                                <div
                                                    class="d-flex gap-3 mb-3 text-muted small flex-wrap align-items-center"
                                                >
                                                    <span
                                                        class="d-flex align-items-center gap-1"
                                                    >
                                                        <i
                                                            class="bi bi-people-fill text-gold"
                                                        ></i>
                                                        <span
                                                            v-if="
                                                                room.min_occupancy &&
                                                                room.min_occupancy >
                                                                    1
                                                            "
                                                            >{{
                                                                room.min_occupancy
                                                            }}
                                                            to
                                                            {{
                                                                room.max_occupancy
                                                            }}
                                                            Guests</span
                                                        >
                                                        <span v-else
                                                            >Max
                                                            {{
                                                                room.max_occupancy
                                                            }}
                                                            Guests</span
                                                        >
                                                    </span>
                                                    <span
                                                        v-if="room.room_size"
                                                        class="bullet-divider opacity-50"
                                                        >•</span
                                                    >
                                                    <span
                                                        v-if="room.room_size"
                                                        class="d-flex align-items-center gap-1"
                                                    >
                                                        <i
                                                            class="bi bi-arrows-fullscreen text-gold"
                                                        ></i>
                                                        {{ room.room_size }} m²
                                                    </span>
                                                </div>

                                                <p
                                                    class="room-card-description text-muted small mb-4 line-clamp-2"
                                                >
                                                    {{ room.description }}
                                                </p>
                                            </div>

                                            <!-- Price & Booking Row -->
                                            <div
                                                class="room-price-cta-row d-flex flex-wrap justify-content-between align-items-center pt-3 border-top mt-auto gap-3"
                                            >
                                                <div
                                                    class="room-price-section d-flex align-items-baseline"
                                                >
                                                    <template
                                                        v-if="room.is_promo"
                                                    >
                                                        <span
                                                            class="currency text-gold fw-bold"
                                                            >₱</span
                                                        >
                                                        <span
                                                            class="h3 serif-font text-gold mb-0 fw-bold"
                                                        >
                                                            {{
                                                                formatPrice(
                                                                    getEffectiveRate(
                                                                        room,
                                                                    ),
                                                                )
                                                            }}
                                                        </span>
                                                        <span
                                                            class="small text-muted ms-1"
                                                        >
                                                            {{
                                                                isPerHead(room)
                                                                    ? "/head/night"
                                                                    : "/night"
                                                            }}
                                                        </span>
                                                        <span
                                                            class="text-muted text-decoration-line-through ms-2 small"
                                                        >
                                                            ₱{{
                                                                formatPrice(
                                                                    getRegularRate(
                                                                        room,
                                                                    ),
                                                                )
                                                            }}
                                                        </span>
                                                    </template>
                                                    <template v-else>
                                                        <span
                                                            class="currency text-gold fw-bold"
                                                            >₱</span
                                                        >
                                                        <span
                                                            class="h3 serif-font text-gold mb-0 fw-bold"
                                                        >
                                                            {{
                                                                formatPrice(
                                                                    room.room_type ===
                                                                        "Family Room" ||
                                                                        room.room_type ===
                                                                            "Barkadahan Room"
                                                                        ? room.price_per_head
                                                                        : room.price_per_night,
                                                                )
                                                            }}
                                                        </span>
                                                        <span
                                                            class="small text-muted ms-1"
                                                        >
                                                            {{
                                                                room.room_type ===
                                                                    "Family Room" ||
                                                                room.room_type ===
                                                                    "Barkadahan Room"
                                                                    ? "/head/night"
                                                                    : "/night"
                                                            }}
                                                        </span>
                                                    </template>
                                                </div>

                                                <button
                                                    @click="
                                                        bookThisRoom(room.id)
                                                    "
                                                    class="btn btn-gold px-4 py-2.5 rounded-pill fw-bold text-uppercase small transition-all btn-book-now text-decoration-none border-0"
                                                >
                                                    Book Room Now
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div
                            class="modal-footer bg-white border-top p-3 d-flex justify-content-between align-items-center"
                        >
                            <span class="small text-muted"
                                ><i
                                    class="bi bi-receipt text-success me-1"
                                ></i>
                                Pay at the Apartelle &middot; Instant Booking Receipt</span
                            >
                            <button
                                type="button"
                                class="btn btn-secondary px-4 py-2 rounded-pill small text-uppercase fw-bold"
                                @click="closeRoomsModal"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ========== GUEST PACKAGE DETAILS MODAL ========== -->
        <Teleport to="body">
            <div
                v-if="showGuestPackageModal && selectedGuestPackage"
                class="modal-backdrop fade show"
                style="z-index: 1050"
                @click="closeGuestPackageModal"
            ></div>
            <div
                v-if="showGuestPackageModal && selectedGuestPackage"
                class="modal fade show d-block"
                tabindex="-1"
                style="z-index: 1055"
                @click.self="closeGuestPackageModal"
            >
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 rounded-4 overflow-hidden shadow-2xl">
                        <!-- Hero Header -->
                        <div class="position-relative flex-shrink-0" style="height: 220px; background: #0f172a;">
                            <img
                                :src="getPackageImage(selectedGuestPackage)"
                                class="w-100 h-100 object-fit-cover"
                                style="opacity: 0.55;"
                            />
                            <div
                                class="position-absolute inset-0"
                                style="background: linear-gradient(180deg, rgba(15, 23, 42, 0.4) 0%, rgba(15, 23, 42, 0.75) 50%, rgba(15, 23, 42, 0.96) 100%);"
                            ></div>

                            <!-- Close Button -->
                            <div class="position-absolute top-0 end-0 p-3 p-md-4">
                                <button
                                    type="button"
                                    class="btn btn-dark bg-dark bg-opacity-75 text-white rounded-circle p-2 d-flex align-items-center justify-content-center border border-white border-opacity-25 shadow-sm"
                                    style="width: 40px; height: 40px; backdrop-filter: blur(10px);"
                                    @click="closeGuestPackageModal"
                                    title="Close"
                                >
                                    <i class="bi bi-x-lg fs-6"></i>
                                </button>
                            </div>

                            <!-- Header Content -->
                            <div class="position-absolute bottom-0 start-0 p-4 p-md-4 text-white w-100" style="padding-bottom: 1.5rem !important;">
                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                    <span
                                        class="badge px-3 py-1.5 rounded-pill x-small fw-bold text-uppercase tracking-wider shadow-sm"
                                        style="background-color: #bc9151 !important; color: #ffffff !important;"
                                    >
                                        Exclusive Tour &amp; Stay Package
                                    </span>
                                    <span
                                        class="badge px-3 py-1.5 rounded-pill x-small fw-bold shadow-sm"
                                        style="background-color: #ffffff !important; color: #0f172a !important; border: 1px solid rgba(255,255,255,0.4);"
                                    >
                                        <i class="bi bi-clock-history me-1 text-gold"></i>{{ selectedGuestPackage.duration || '3 Days, 2 Nights' }}
                                    </span>
                                    <span
                                        class="badge px-3 py-1.5 rounded-pill x-small fw-bold shadow-sm"
                                        style="background-color: rgba(255,255,255,0.2) !important; color: #ffffff !important; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3);"
                                    >
                                        <i class="bi bi-people-fill me-1"></i>{{ selectedGuestPackage.pax || 'Good for 2 pax' }}
                                    </span>
                                </div>

                                <h2
                                    class="serif-font fw-bold mb-1 text-white fs-2"
                                    style="text-shadow: 0 2px 8px rgba(0,0,0,0.9); letter-spacing: -0.5px;"
                                >
                                    {{ selectedGuestPackage.name }}
                                </h2>
                                <p
                                    class="mb-0 text-white small fw-semibold"
                                    style="text-shadow: 0 1px 4px rgba(0,0,0,0.95); opacity: 0.95;"
                                    v-if="selectedGuestPackage.tagline"
                                >
                                    <i class="bi bi-geo-alt-fill text-gold me-1"></i>{{ selectedGuestPackage.tagline }}
                                </p>
                            </div>
                        </div>

                        <!-- Modal Body -->
                        <div class="p-4 p-md-4 overflow-y-auto flex-grow-1 d-flex flex-column gap-4 bg-cream-soft">
                            <!-- Rate & Highlights Bar -->
                            <div class="p-3.5 p-md-4 rounded-4 bg-white border d-flex flex-wrap justify-content-between align-items-center gap-3 shadow-xs">
                                <div>
                                    <span class="text-muted xx-small fw-bold text-uppercase tracking-wider d-block mb-1">Package Total Rate</span>
                                    <div class="d-flex align-items-baseline gap-2">
                                        <span class="h2 fw-bold text-gold serif-font mb-0" style="color: #bc9151 !important;">
                                            ₱{{ formatPrice(selectedGuestPackage.price) }}
                                        </span>
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

                            <!-- Description -->
                            <div v-if="selectedGuestPackage.description" class="px-2">
                                <p class="text-secondary-dark small mb-0" style="line-height: 1.7; font-size: 0.9rem;">
                                    {{ selectedGuestPackage.description }}
                                </p>
                            </div>

                            <!-- Accommodation Details -->
                            <div class="p-4 rounded-4 bg-white border shadow-xs">
                                <div class="row g-4 align-items-center">
                                    <div class="col-md-5 col-lg-5">
                                        <div class="rounded-3 overflow-hidden position-relative shadow-xs bg-light" style="height: 190px;">
                                            <img
                                                :src="getRoomImage(selectedGuestPackage.room)"
                                                :alt="selectedGuestPackage.room?.room_type || 'Room'"
                                                class="w-100 h-100 object-fit-cover transition-all"
                                                @error="onImageError($event, selectedGuestPackage.room?.room_type)"
                                            />
                                            <span class="position-absolute top-0 start-0 m-2.5 badge bg-dark bg-opacity-80 text-white shadow-sm px-3 py-1.5 rounded-pill x-small fw-bold border border-white border-opacity-25 backdrop-blur">
                                                <i class="bi bi-door-closed-fill me-1 text-gold"></i>
                                                {{ selectedGuestPackage.room ? `Room #${selectedGuestPackage.room.room_number}` : 'Private Room' }}
                                            </span>
                                        </div>
                                    </div>

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

                                            <!-- Room Specs -->
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

                                        <!-- Amenities -->
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

                            <!-- Islands to Visit & Tour Inclusions -->
                            <div class="row g-4">
                                <!-- Islands to Visit -->
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

                                <!-- Tour Inclusions -->
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

                            <!-- Location & Contact -->
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
        </Teleport>
    </div>
</template>

<style scoped>
/* ========== HERO SECTION ========== */
.hero-section {
    background: url("/images/unsplash/emespic.jpg") center/cover no-repeat;
    position: relative;
    min-height: 100vh;
    display: flex;
    align-items: center;
    background-attachment: fixed;
    padding-top: 72px; /* Offset for fixed navbar */
    padding-bottom: 1.5rem;
}

.hero-content-row {
    min-height: calc(100vh - 90px);
    align-items: center;
    padding: 0.75rem 0;
}

.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to right,
        rgba(26, 38, 52, 0.95) 0%,
        rgba(26, 38, 52, 0.72) 50%,
        rgba(26, 38, 52, 0.45) 100%
    );
}

.hero-title {
    font-size: clamp(2.6rem, 4.3vw, 4.1rem);
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 1.25rem;
    letter-spacing: -1.5px;
    text-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}

.hero-subtitle {
    font-size: 1.1rem;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.85);
    max-width: 520px;
    margin-bottom: 2rem;
    font-weight: 400;
}

/* ========== DESIGNER LABEL ========== */
.designer-label {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: fit-content;
    justify-content: center;
}

.label-dot {
    width: 5px;
    height: 5px;
    background-color: var(--primary-gold);
    border-radius: 50%;
    display: inline-block;
    opacity: 1;
}

.label-text {
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 5px;
    color: var(--primary-gold);
    text-transform: uppercase;
}

/* ========== CTA SECTION ========== */
.cta-blur-blob {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 500px;
    height: 500px;
    background: radial-gradient(
        circle,
        rgba(188, 145, 81, 0.08) 0%,
        transparent 70%
    );
    filter: blur(60px);
    z-index: 0;
    pointer-events: none;
}

/* ========== SPACING & UTILITIES ========== */
.py-7 {
    padding-top: 8rem;
    padding-bottom: 8rem;
}

@media (max-width: 767.98px) {
    .py-7 {
        padding-top: 5rem;
        padding-bottom: 5rem;
    }
}

.bg-white-10 {
    background-color: rgba(255, 255, 255, 0.1);
}
.border-white-10 {
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.border-white-20 {
    border-color: rgba(255, 255, 255, 0.2) !important;
}
.backdrop-blur {
    backdrop-filter: blur(12px);
}

/* ========== PREMIUM BOOKING CARD ========== */
.glass-morph {
    background: rgba(255, 255, 255, 0.96);
    backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 2.25rem;
}

.booking-card-premium {
    transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.form-label-premium {
    display: block;
    font-size: 0.74rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 1.2px;
    margin-bottom: 0.5rem;
}

.input-wrapper-premium {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1.15rem;
    color: var(--primary-gold);
    font-size: 1.1rem;
    pointer-events: none;
}

.form-control-premium {
    width: 100%;
    padding: 0.95rem 1rem 0.95rem 3rem;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    font-size: 0.94rem;
    font-weight: 500;
    transition: all 0.3s ease;
    color: #1e293b;
}

.form-control-premium:focus {
    background-color: white;
    border-color: var(--primary-gold);
    box-shadow: 0 10px 25px rgba(188, 145, 81, 0.12);
    outline: none;
}

.form-control-premium.border-gold {
    background-color: white;
    border-color: var(--primary-gold) !important;
}

.border-gold {
    border-color: var(--primary-gold) !important;
}

.shadow-gold-sm {
    box-shadow: 0 10px 25px rgba(188, 145, 81, 0.15) !important;
}

.select-none {
    user-select: none;
}

.calendar-popup {
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.18);
    min-width: 295px;
    z-index: 1050;
}

@media (max-width: 575.98px) {
    .calendar-popup {
        min-width: 100%;
        left: 0 !important;
        right: 0 !important;
    }
}

.btn-gold-premium {
    background: linear-gradient(135deg, var(--primary-gold) 0%, #a37d45 100%);
    color: white;
    border: none;
    border-radius: 1.1rem;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-gold-premium:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(188, 145, 81, 0.4);
    filter: brightness(1.1);
}

.shadow-2xl {
    box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.25);
}

/* Room Cards */
.room-card {
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

.room-card:hover {
    transform: translateY(-12px);
    box-shadow: 0 25px 55px rgba(188, 145, 81, 0.15) !important;
}

.room-card-image {
    height: 280px;
    overflow: hidden;
    position: relative;
}

.room-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.room-card:hover .room-card-image img {
    transform: scale(1.1);
}

.room-badge {
    position: absolute;
    top: 1.25rem;
    left: 1.25rem;
    padding: 0.45rem 1.25rem;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    border-radius: 50px;
    letter-spacing: 0.5px;
    z-index: 2;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.badge-available {
    background: #10b981 !important;
    color: #ffffff !important;
}

.badge-unavailable {
    background: #ef4444 !important;
    color: #ffffff !important;
}

.room-size-tag {
    position: absolute;
    bottom: 1.25rem;
    right: 1.25rem;
    background: rgba(255, 255, 255, 0.95);
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--secondary-dark);
    backdrop-filter: blur(5px);
    z-index: 2;
}

.room-card-price {
    display: flex;
    align-items: baseline;
    gap: 0.3rem;
}

.room-card-price .currency {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text-dark);
}

.room-card-price .price {
    font-family: var(--font-serif);
    font-weight: 700;
    font-size: 1.8rem;
    color: var(--primary-gold);
}

.room-card-price .period {
    font-size: 0.85rem;
    color: #94a3b8;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.room-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.feature-pill {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    padding: 0.4rem 0.9rem;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: #64748b;
    font-weight: 700;
}

.feature-pill i {
    color: var(--primary-gold);
    font-size: 1.1rem;
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

/* Horizontal Room Card */
.room-horizontal-card {
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.room-horizontal-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
}

.room-horizontal-card .room-card-image {
    width: 100%;
    height: 220px;
}

.room-horizontal-card .room-card-image img {
    transition: transform 1.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.room-horizontal-card:hover .room-card-image img {
    transform: scale(1.08);
}

.room-horizontal-card .room-card-body {
    padding: 1.5rem;
}

@media (min-width: 768px) {
    .room-horizontal-card {
        flex-direction: row !important;
    }
    .room-horizontal-card .room-card-image {
        width: 320px;
        height: auto !important;
        min-height: 250px;
    }
    .room-horizontal-card .room-card-body {
        padding: 1.75rem 2rem;
    }
}

/* Amenities Card */
.amenity-card-premium {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.amenity-card-premium:hover {
    background: white !important;
    box-shadow: 0 20px 40px rgba(188, 145, 81, 0.12);
    transform: translateY(-8px);
}

.icon-box-gold {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-gold-subtle);
    color: var(--primary-gold);
    border-radius: 20px;
    font-size: 1.6rem;
}

/* Testimonials */
.testimonials-section {
    background-color: #0f172a;
    padding: 8rem 0;
}

.testimonial-card-premium {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.06) !important;
    backdrop-filter: blur(15px);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.testimonial-card-premium:hover {
    background: rgba(255, 255, 255, 0.06) !important;
    transform: translateY(-5px);
}

.testimonial-avatar {
    object-fit: cover;
}

.line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (max-width: 991.98px) {
    .hero-section {
        min-height: auto;
        padding-top: 96px;
        padding-bottom: 3.5rem;
    }
    .hero-content-row {
        min-height: auto;
    }
    .hero-title {
        font-size: clamp(2.3rem, 6vw, 3.25rem);
    }
}

@media (max-width: 767.98px) {
    .hero-section {
        padding-bottom: 3rem;
    }
}

/* Utilities */
.hover-scale {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hover-scale:hover {
    transform: scale(1.05);
}
.shadow-gold {
    box-shadow: 0 10px 30px rgba(188, 145, 81, 0.4);
}

/* ========== SKELETON LOADING ========== */
.skeleton-image {
    background: #e2e8f0;
}

.skeleton-line {
    background: #e2e8f0;
    border-radius: 8px;
}

.skeleton-btn {
    background: #e2e8f0;
}

.skeleton-shimmer {
    position: relative;
    overflow: hidden;
}

.skeleton-shimmer::after {
    content: "";
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
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* ========== ROOM AVAILABILITY MODAL ========== */
.modal-backdrop.show {
    opacity: 0.65;
    background-color: #0f172a;
    backdrop-filter: blur(4px);
}

.modal.show {
    display: block;
}

.bullet-divider {
    color: #cbd5e1;
    font-size: 0.8rem;
}

.feature-badge {
    background-color: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.room-price-section {
    border-color: #f1f5f9 !important;
}

.room-price-section .currency {
    font-size: 1rem;
    margin-right: 2px;
}

.bg-cream {
    background-color: #fcfbf9 !important;
}

.shadow-2xl {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
}

.animate-fade-up {
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ========== CLEAN MODERN PROMO BANNER ========== */
.promo-clean-banner {
    background: #ffffff;
    border: 1px solid rgba(188, 145, 81, 0.22) !important;
    box-shadow:
        0 12px 35px -10px rgba(188, 145, 81, 0.12),
        0 4px 16px rgba(0, 0, 0, 0.03) !important;
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 0 !important;
    display: flex;
    flex-direction: column;
    height: 100%;
    flex: 1 1 auto;
}

.promo-gold-accent-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px !important;
    background: linear-gradient(90deg, #bc9151 0%, #dfb26b 50%, #bc9151 100%);
    z-index: 3;
    pointer-events: none;
}

.promo-campaign-badge {
    background-color: #fff1f2 !important;
    color: #e11d48 !important;
    border: 1.5px solid #fecdd3 !important;
    font-size: 0.8rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 2px 8px rgba(225, 29, 72, 0.1) !important;
}

.promo-campaign-badge i {
    color: #e11d48 !important;
}

.btn-promo-deal {
    background: linear-gradient(135deg, #c59b5f 0%, #bc9151 50%, #a47c3e 100%) !important;
    border: none !important;
    color: #ffffff !important;
    box-shadow: 0 4px 15px rgba(188, 145, 81, 0.35) !important;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.btn-promo-deal:hover {
    background: linear-gradient(135deg, #d4af37 0%, #c59b5f 50%, #bc9151 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(188, 145, 81, 0.5) !important;
    color: #ffffff !important;
}

.promo-perk-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #334155 !important;
    background: #f8fafc;
    padding: 0.35rem 0.8rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.promo-arrow-btn {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 0.7rem;
    background: #f8fafc;
    transition: all 0.2s ease;
}

.promo-arrow-btn:hover {
    background: #bc9151;
    border-color: #bc9151;
    color: #ffffff;
}

.promo-clean-photo-frame {
    height: 100%;
    min-height: 380px;
    background: #f1f5f9;
}

.promo-clean-zoom {
    transition: transform 0.5s ease;
}

.promo-clean-photo-frame:hover .promo-clean-zoom {
    transform: scale(1.04);
}

.bg-gradient-clean-caption {
    background: linear-gradient(
        180deg,
        rgba(0, 0, 0, 0) 0%,
        rgba(15, 23, 42, 0.85) 45%,
        rgba(15, 23, 42, 0.98) 100%
    ) !important;
}

/* Smooth slide cross-fade transition */
.promo-slide-fade-enter-active,
.promo-slide-fade-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s ease;
}

.promo-slide-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.promo-slide-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (max-width: 991.98px) {
    .promo-clean-photo-frame {
        height: 280px;
        min-height: 280px;
        margin-top: 0.5rem;
    }
}

.package-clean-banner {
    background: #ffffff;
    border: 1px solid rgba(13, 148, 136, 0.22) !important;
    box-shadow:
        0 12px 35px -10px rgba(13, 148, 136, 0.12),
        0 4px 16px rgba(0, 0, 0, 0.03) !important;
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 0 !important;
    display: flex;
    flex-direction: column;
    height: 100%;
    flex: 1 1 auto;
}

.package-clean-banner:hover,
.promo-clean-banner:hover {
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08) !important;
}

.promo-card-photo-frame {
    height: 220px;
    background: #0f172a;
}

.package-teal-accent-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px !important;
    background: linear-gradient(90deg, #0d9488 0%, #14b8a6 50%, #0d9488 100%);
    z-index: 3;
    pointer-events: none;
}

.package-campaign-badge {
    background-color: #f0fdfa !important;
    color: #0f766e !important;
    border: 1.5px solid #99f6e4 !important;
    font-size: 0.8rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.05em !important;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 2px 8px rgba(13, 148, 136, 0.12) !important;
}

.package-campaign-badge i {
    color: #0d9488 !important;
}

.btn-package-deal {
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;
    border: none !important;
    color: #ffffff !important;
    box-shadow: 0 4px 15px rgba(13, 148, 136, 0.35) !important;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.btn-package-deal:hover {
    background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 24px rgba(13, 148, 136, 0.5) !important;
    color: #ffffff !important;
}

.text-teal {
    color: #0d9488 !important;
}

/* Card Inner Alignments */
.promo-slide-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    flex: 1 1 auto;
}

.promo-title-row {
    min-height: 38px;
}

.promo-deal-desc {
    min-height: 44px;
    font-size: 0.92rem;
    line-height: 1.55;
    color: #475569 !important;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.promo-perks-box {
    min-height: 76px;
    align-content: flex-start;
}

.promo-badge-slot {
    min-height: 26px;
    display: flex;
    align-items: center;
}

</style>
