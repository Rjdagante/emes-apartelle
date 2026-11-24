<template>
    <div class="result-page bg-cream py-5">
        <div class="container py-4">
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-5">
                <div
                    class="spinner-border text-gold"
                    role="status"
                    style="width: 3rem; height: 3rem"
                >
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted fw-bold">
                    Loading your booking receipt...
                </p>
            </div>

            <!-- Success Content Grid -->
            <div
                v-else
                class="row g-4 justify-content-center align-items-stretch"
            >
                <!-- Left Column: Confirmation Card -->
                <div
                    :class="
                        reservation
                            ? 'col-lg-5 col-md-6 d-flex'
                            : 'col-md-8 col-lg-6 d-flex'
                    "
                >
                    <div
                        class="result-card animate-fade-up w-100 d-flex flex-column justify-content-between"
                    >
                        <div
                            class="flex-grow-1 d-flex flex-column justify-content-center"
                        >
                            <div class="result-icon success">
                                <i class="bi bi-check-lg"></i>
                            </div>
                            <h1 class="result-title serif-font">
                                {{
                                    reservation?.status === "confirmed"
                                        ? "Booking Confirmed!"
                                        : "Reservation Received!"
                                }}
                            </h1>
                            <p class="result-message mb-4">
                                Thank you for choosing EME's Apartelle. Your
                                reservation has been recorded.<br />
                                <span v-if="isHalfPayment && !isPaid">
                                    Please save or print your receipt. Pay the
                                    other half when you walk in.
                                </span>
                                <span v-else-if="isPaid || hasPaymentProof">
                                    Your payment has been recorded. We look
                                    forward to welcoming you.
                                </span>
                                <span v-else>
                                    Please save or print your receipt and settle
                                    the total amount at the front desk upon
                                    check-in.
                                </span>
                            </p>

                            <div
                                v-if="reservation"
                                class="total-pill mx-auto mb-4 px-4 py-3 rounded-4"
                            >
                                <template v-if="isHalfPayment && !isPaid">
                                    <div class="mb-2 pb-2 border-bottom border-gold-subtle">
                                        <span
                                            class="d-block text-uppercase fw-bold meta-label mb-1"
                                        >
                                            Amount Paid (50% Downpayment)
                                        </span>
                                        <span
                                            class="serif-font fw-bold text-success"
                                            style="font-size: 1.5rem"
                                        >
                                            ₱{{ formatPrice(amountPaid) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span
                                            class="d-block text-uppercase fw-bold meta-label mb-1"
                                        >
                                            Remaining Balance
                                        </span>
                                        <span
                                            class="serif-font fw-bold text-gold"
                                            style="font-size: 1.5rem"
                                        >
                                            ₱{{ formatPrice(remainingBalance) }}
                                        </span>
                                        <span
                                            class="d-block small text-muted mt-1 fw-medium"
                                        >
                                            Pay the other half when you walk in
                                        </span>
                                    </div>
                                </template>
                                <template v-else>
                                    <span
                                        class="d-block text-uppercase fw-bold meta-label mb-1"
                                    >
                                        {{
                                            isPaid || hasPaymentProof
                                                ? "Amount Paid"
                                                : "Total Amount Due"
                                        }}
                                    </span>
                                    <span
                                        class="serif-font fw-bold text-gold"
                                        style="font-size: 1.75rem"
                                    >
                                        ₱{{
                                            formatPrice(
                                                isPaid || hasPaymentProof
                                                    ? amountPaid
                                                    : totalAmount,
                                            )
                                        }}
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="result-actions pt-4 border-top mt-auto">
                            <router-link
                                to="/my-bookings"
                                class="btn btn-gold rounded-pill w-100 py-3 fw-bold text-uppercase text-white border-0 shadow-sm d-flex align-items-center justify-content-center gap-2"
                            >
                                <i class="bi bi-journal-check"></i> View My
                                Bookings
                            </router-link>
                            <router-link
                                to="/rooms"
                                class="btn btn-outline-dark-custom rounded-pill w-100 py-3 fw-bold text-uppercase small d-flex align-items-center justify-content-center gap-2"
                            >
                                <i class="bi bi-calendar-plus"></i> Book Another
                                Room
                            </router-link>
                            <router-link
                                to="/"
                                class="btn btn-link text-muted text-decoration-none fw-semibold small py-1"
                            >
                                Back to Home
                            </router-link>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Booking Receipt Card -->
                <div v-if="reservation" class="col-lg-6 col-md-6 d-flex">
                    <div
                        class="receipt-card animate-fade-up delay-1 w-100 d-flex flex-column justify-content-between"
                    >
                        <div>
                            <!-- Receipt Header -->
                            <div class="receipt-header">
                                <div
                                    class="d-flex justify-content-between align-items-start"
                                >
                                    <div>
                                        <h4
                                            class="serif-font fw-bold text-gold mb-0"
                                        >
                                            EME's Apartelle
                                        </h4>
                                        <span class="receipt-subtitle"
                                            >Booking Receipt</span
                                        >
                                    </div>
                                    <div class="text-end">
                                        <span
                                            class="badge px-3 py-2 rounded-pill"
                                            :class="
                                                getPaymentBadgeClass(
                                                    reservation.payment_status,
                                                )
                                            "
                                        >
                                            {{
                                                formatPaymentStatus(
                                                    reservation.payment_status,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    class="receipt-meta row g-2 mt-3 pt-3 border-top border-light"
                                >
                                    <div class="col-6">
                                        <span class="meta-label"
                                            >Receipt No.</span
                                        >
                                        <span class="meta-value"
                                            >#EME-{{
                                                String(reservation.id).padStart(
                                                    5,
                                                    "0",
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="meta-label"
                                            >Date Issued</span
                                        >
                                        <span class="meta-value">{{
                                            formatDate(
                                                reservation.created_at ||
                                                    new Date(),
                                            )
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Receipt Body -->
                            <div class="receipt-body">
                                <!-- Guest Info -->
                                <div class="receipt-section">
                                    <h6 class="section-title">
                                        <i
                                            class="bi bi-person me-1.5 text-gold"
                                        ></i>
                                        Guest Details
                                    </h6>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <span class="field-label"
                                                >Name</span
                                            >
                                            <span class="field-value">{{
                                                reservation.guest_name ||
                                                reservation.user?.name ||
                                                "Guest"
                                            }}</span>
                                        </div>
                                        <div
                                            class="col-6 text-end"
                                            v-if="reservation.user?.email"
                                        >
                                            <span class="field-label"
                                                >Email</span
                                            >
                                            <span
                                                class="field-value text-truncate d-block"
                                                >{{
                                                    reservation.user?.email
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Reservation Info -->
                                <div
                                    class="receipt-section mt-4 pt-3 border-top border-dashed"
                                >
                                    <h6 class="section-title">
                                        <i
                                            class="bi bi-key me-1.5 text-gold"
                                        ></i>
                                        Booking Details
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <span class="field-label"
                                                >Room Type &amp; Number</span
                                            >
                                            <span class="field-value fw-bold"
                                                >Room #{{
                                                    reservation.room
                                                        ?.room_number
                                                }}
                                                ({{
                                                    reservation.room?.room_type
                                                }})</span
                                            >
                                        </div>
                                        <div class="col-6 text-end">
                                            <span class="field-label"
                                                >Stay Duration</span
                                            >
                                            <span class="field-value"
                                                >{{ totalNights }} Night{{
                                                    totalNights > 1 ? "s" : ""
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="col-6"
                                            v-if="reservation.package"
                                        >
                                            <span class="field-label"
                                                >Tour Package</span
                                            >
                                            <span
                                                class="field-value fw-bold text-gold"
                                                >{{
                                                    reservation.package.name
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            :class="
                                                reservation.package
                                                    ? 'col-6 text-end'
                                                    : 'col-12'
                                            "
                                        >
                                            <span class="field-label"
                                                >Guests</span
                                            >
                                            <span class="field-value">
                                                {{ guestSummary }}
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <span class="field-label"
                                                >Check-In</span
                                            >
                                            <span class="field-value"
                                                >{{
                                                    formatDate(
                                                        reservation.check_in,
                                                    )
                                                }}
                                                (1:00 PM)</span
                                            >
                                        </div>
                                        <div class="col-6 text-end">
                                            <span class="field-label"
                                                >Check-Out</span
                                            >
                                            <span class="field-value"
                                                >{{
                                                    formatDate(
                                                        reservation.check_out,
                                                    )
                                                }}
                                                (11:00 AM)</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Financial Breakdown -->
                                <div
                                    class="receipt-section mt-4 pt-3 border-top border-dashed"
                                >
                                    <h6 class="section-title">
                                        <i
                                            class="bi bi-receipt me-1.5 text-gold"
                                        ></i>
                                        Charges Summary
                                    </h6>

                                    <template v-if="reservation.package">
                                        <div
                                            class="price-row d-flex justify-content-between mb-2"
                                        >
                                            <span class="price-label"
                                                >Package Rate ({{
                                                    reservation.package.name
                                                }})</span
                                            >
                                            <span class="price-val"
                                                >₱{{
                                                    formatPrice(
                                                        reservation.package
                                                            .price,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </template>
                                    <template v-else-if="isPerHeadRoom">
                                        <div
                                            class="price-row d-flex justify-content-between mb-2"
                                        >
                                            <span class="price-label">
                                                Room Rate ({{
                                                    totalNights
                                                }}
                                                Night{{
                                                    totalNights > 1 ? "s" : ""
                                                }}
                                                &times;
                                                {{ chargeableGuests }} Paying
                                                Guest{{
                                                    chargeableGuests > 1
                                                        ? "s"
                                                        : ""
                                                }})
                                            </span>
                                            <span class="price-val"
                                                >₱{{
                                                    formatPrice(effectiveRate)
                                                }}
                                                / head / night</span
                                            >
                                        </div>
                                        <div
                                            v-if="freeChildren > 0"
                                            class="price-row d-flex justify-content-between mb-2 text-success"
                                        >
                                            <span
                                                class="price-label text-success"
                                                >Children under 7 ({{
                                                    freeChildren
                                                }})</span
                                            >
                                            <span
                                                class="price-val fw-bold text-success"
                                                >FREE (₱0)</span
                                            >
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div
                                            class="price-row d-flex justify-content-between mb-2"
                                        >
                                            <span class="price-label"
                                                >Room Rate ({{
                                                    totalNights
                                                }}
                                                Night{{
                                                    totalNights > 1 ? "s" : ""
                                                }})</span
                                            >
                                            <span class="price-val"
                                                >₱{{
                                                    formatPrice(effectiveRate)
                                                }}
                                                / night</span
                                            >
                                        </div>
                                    </template>

                                    <div
                                        v-if="
                                            reservation.extra_guests > 0 ||
                                            Number(
                                                reservation.extra_bed_charge,
                                            ) > 0
                                        "
                                        class="price-row d-flex justify-content-between mb-2 text-gold"
                                    >
                                        <span class="price-label">
                                            <i
                                                class="bi bi-plus-circle me-1"
                                            ></i>
                                            Additional Beddings ({{
                                                reservation.extra_guests
                                            }}
                                            extra bed{{
                                                reservation.extra_guests > 1
                                                    ? "s"
                                                    : ""
                                            }})
                                        </span>
                                        <span class="price-val fw-bold"
                                            >+₱{{
                                                formatPrice(
                                                    reservation.extra_bed_charge ||
                                                        reservation.extra_guests *
                                                            (reservation.room
                                                                ?.extra_bed_price ||
                                                                300) *
                                                            totalNights,
                                                )
                                            }}</span
                                        >
                                    </div>

                                    <div
                                        class="price-row d-flex justify-content-between mb-2 pt-2 border-top border-light"
                                    >
                                        <span class="price-label"
                                            >Subtotal</span
                                        >
                                        <span class="price-val"
                                            >₱{{
                                                formatPrice(
                                                    reservation.total_amount,
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="price-row d-flex justify-content-between text-success"
                                    >
                                        <span class="price-label"
                                            >Taxes &amp; Service Fees</span
                                        >
                                        <span class="price-val fw-bold"
                                            >INCLUDED</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Receipt Footer / Total -->
                        <div
                            class="receipt-footer mt-4 pt-3 border-top border-3"
                        >
                            <template v-if="isHalfPayment && !isPaid">
                                <div
                                    class="price-row d-flex justify-content-between mb-2"
                                >
                                    <span class="price-label"
                                        >Total Amount</span
                                    >
                                    <span class="price-val fw-bold"
                                        >₱{{ formatPrice(totalAmount) }}</span
                                    >
                                </div>
                                <div
                                    class="price-row d-flex justify-content-between mb-2 text-success"
                                >
                                    <span class="price-label text-success"
                                        >Amount Paid (50% Downpayment)</span
                                    >
                                    <span class="price-val fw-bold text-success"
                                        >₱{{ formatPrice(amountPaid) }}</span
                                    >
                                </div>
                                <div
                                    class="d-flex justify-content-between align-items-center mb-1 pt-2 border-top border-light"
                                >
                                    <span
                                        class="total-label serif-font h5 fw-bold text-dark mb-0"
                                    >
                                        Remaining Balance
                                    </span>
                                    <span
                                        class="total-amount text-gold serif-font h3 fw-bold mb-0"
                                    >
                                        ₱{{ formatPrice(remainingBalance) }}
                                    </span>
                                </div>
                                <p class="small text-muted mb-3">
                                    Pay the other half when you walk in.
                                </p>
                            </template>
                            <template v-else>
                                <div
                                    class="d-flex justify-content-between align-items-center mb-2 pt-1"
                                >
                                    <span
                                        class="total-label serif-font h5 fw-bold text-dark mb-0"
                                    >
                                        {{
                                            isPaid || hasPaymentProof
                                                ? "Amount Paid"
                                                : "Total Amount Due"
                                        }}
                                    </span>
                                    <span
                                        class="total-amount text-gold serif-font h3 fw-bold mb-0"
                                    >
                                        ₱{{
                                            formatPrice(
                                                isPaid || hasPaymentProof
                                                    ? amountPaid
                                                    : totalAmount,
                                            )
                                        }}
                                    </span>
                                </div>
                                <p
                                    v-if="!isPaid && !hasPaymentProof"
                                    class="small text-muted mb-3"
                                >
                                    Payment is settled at the front desk upon
                                    check-in.
                                </p>
                                <p
                                    v-else-if="!isPaid && hasPaymentProof"
                                    class="small text-muted mb-3"
                                >
                                    Payment proof submitted for verification.
                                </p>
                            </template>

                            <div class="text-center pt-2">
                                <p class="receipt-thankyou mb-3 italic-text">
                                    Thank you for choosing EME's Apartelle!
                                </p>
                                <div
                                    class="d-flex gap-2 justify-content-center flex-wrap no-print"
                                >
                                    <button
                                        @click="printReceipt"
                                        class="btn btn-sm btn-outline-dark-custom rounded-pill px-4 py-2 fw-bold small d-flex align-items-center gap-2 transition-all"
                                    >
                                        <i class="bi bi-printer"></i> Print
                                        Receipt
                                    </button>
                                    <button
                                        @click="downloadPdf"
                                        class="btn btn-sm btn-gold rounded-pill px-4 py-2 fw-bold small text-white border-0 d-flex align-items-center gap-2 transition-all"
                                    >
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        Download PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";

const route = useRoute();
const reservation = ref(null);
const loading = ref(true);

const ensureAuthToken = () => {
    const token = localStorage.getItem("token");
    if (token && !axios.defaults.headers.common["Authorization"]) {
        axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
    }
};

const fetchReservation = async (resId) => {
    try {
        ensureAuthToken();
        const response = await axios.get(`/api/reservations/${resId}`);
        reservation.value = response.data;
    } catch (error) {
        console.error("Error fetching reservation details:", error);
    }
};

onMounted(async () => {
    ensureAuthToken();
    const resId = route.query.res_id;
    if (resId) {
        loading.value = true;
        await fetchReservation(resId);
    }
    loading.value = false;
});

const isPaid = computed(() => reservation.value?.payment_status === "paid");

const isHalfPayment = computed(
    () => reservation.value?.payment_option === "half",
);

const hasPaymentProof = computed(() =>
    Boolean(
        reservation.value?.payment_screenshot ||
            reservation.value?.payment_screenshot_url,
    ),
);

const totalAmount = computed(() =>
    Number(reservation.value?.total_amount || 0),
);

const amountPaid = computed(() => {
    if (!reservation.value) return 0;
    if (isPaid.value) return totalAmount.value;
    if (isHalfPayment.value) {
        return Number(
            reservation.value.downpayment_amount || totalAmount.value / 2,
        );
    }
    if (hasPaymentProof.value) {
        return totalAmount.value;
    }
    return 0;
});

const remainingBalance = computed(() => {
    if (!reservation.value || isPaid.value) return 0;
    if (isHalfPayment.value) {
        return Math.max(0, totalAmount.value - amountPaid.value);
    }
    if (hasPaymentProof.value) return 0;
    return totalAmount.value;
});

const isPerHeadRoom = computed(() => {
    const type = reservation.value?.room?.room_type;
    return type === "Family Room" || type === "Barkadahan Room";
});

const childrenAges = computed(() => {
    const raw = reservation.value?.children_ages;
    if (Array.isArray(raw)) return raw;
    if (typeof raw === "string") {
        try {
            return JSON.parse(raw) || [];
        } catch {
            return [];
        }
    }
    return [];
});

const freeChildren = computed(
    () => childrenAges.value.filter((a) => Number(a) < 7).length,
);
const payingChildren = computed(
    () => childrenAges.value.filter((a) => Number(a) >= 7).length,
);

const chargeableGuests = computed(() => {
    const adults = Number(
        reservation.value?.adults ?? reservation.value?.guests ?? 1,
    );
    return Math.max(1, adults + payingChildren.value);
});

const guestSummary = computed(() => {
    const adults = Number(
        reservation.value?.adults ?? reservation.value?.guests ?? 1,
    );
    const children = Number(reservation.value?.children ?? 0);
    let text = `${adults} Adult${adults > 1 ? "s" : ""}`;
    if (children > 0) {
        text += `, ${children} Child${children > 1 ? "ren" : ""}`;
    }
    return text;
});

const effectiveRate = computed(() => {
    const room = reservation.value?.room;
    if (!room) return 0;
    if (isPerHeadRoom.value) {
        return room.is_promo && Number(room.promo_price_per_head) > 0
            ? Number(room.promo_price_per_head)
            : Number(room.price_per_head || 0);
    }
    return room.is_promo && Number(room.promo_price) > 0
        ? Number(room.promo_price)
        : Number(room.price_per_night || 0);
});

const totalNights = computed(() => {
    if (!reservation.value?.check_in || !reservation.value?.check_out) return 0;
    const start = new Date(reservation.value.check_in);
    const end = new Date(reservation.value.check_out);
    const diff = end - start;
    const nights = Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
    return nights > 0 ? nights : 1;
});

const formatDate = (dateString) => {
    if (!dateString) return "";
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const formatPrice = (price) => {
    return parseFloat(price || 0).toLocaleString();
};

const formatPaymentStatus = (paymentStatus) => {
    switch (paymentStatus?.toLowerCase()) {
        case "paid":
            return "PAID";
        case "partially_paid":
            return "PARTIALLY PAID";
        case "refunded":
            return "REFUNDED";
        case "unpaid":
        default:
            return "PAY AT HOTEL";
    }
};

const getPaymentBadgeClass = (paymentStatus) => {
    switch (paymentStatus?.toLowerCase()) {
        case "paid":
            return "bg-success text-white";
        case "partially_paid":
            return "bg-warning text-dark";
        case "unpaid":
            return "bg-gold-subtle text-gold border border-gold-light";
        case "refunded":
            return "bg-info text-white";
        default:
            return "bg-secondary text-white";
    }
};

const printReceipt = () => {
    window.print();
};

const downloadPdf = () => {
    if (reservation.value?.id) {
        window.open(`/invoices/${reservation.value.id}/download`, "_blank");
    }
};
</script>

<style scoped>
.result-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 100px 1rem 3rem 1rem !important;
    background-color: var(--bg-cream);
}

.result-card {
    background: white;
    border-radius: 24px;
    padding: 3rem 2.5rem;
    text-align: center;
    box-shadow: var(--shadow-xl);
    border: 1px solid rgba(0, 0, 0, 0.02);
}

.result-icon {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 2rem;
}

.result-icon.success {
    background: #ecfdf5;
    color: #22c55e;
}

.result-icon i {
    font-size: 2.8rem;
}

.result-title {
    font-family: var(--font-serif);
    font-weight: 700;
    font-size: 2.2rem;
    margin-bottom: 1.25rem;
    color: var(--text-dark);
}

.result-message {
    color: var(--text-muted);
    font-size: 1rem;
    line-height: 1.7;
}

.total-pill {
    background-color: #fdfbf7;
    border: 1px solid rgba(188, 145, 81, 0.25);
    min-width: 220px;
}

.result-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.result-actions .btn {
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

/* Receipt Card */
.receipt-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: 0 15px 35px rgba(188, 145, 81, 0.1);
    border: 1px solid rgba(188, 145, 81, 0.15);
    position: relative;
    overflow: hidden;
}

.receipt-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #bc9151 0%, #d4af37 50%, #bc9151 100%);
}

.receipt-header {
    margin-bottom: 1.5rem;
}

.receipt-subtitle {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #718096;
    font-weight: 700;
    display: block;
    margin-top: 2px;
}

.receipt-meta {
    font-size: 0.85rem;
}

.meta-label {
    display: block;
    color: #a0aec0;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.meta-value {
    font-weight: 700;
    color: #2d3748;
}

.section-title {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #bc9151;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.field-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #a0aec0;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.field-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #2d3748;
}

.price-row {
    font-size: 0.9rem;
    font-weight: 600;
}

.price-label {
    color: #718096;
}

.price-val {
    color: #2d3748;
}

.total-label {
    font-size: 1.1rem;
}

.total-amount {
    font-size: 1.8rem;
    letter-spacing: -0.5px;
}

.receipt-thankyou {
    font-size: 0.9rem;
    color: #718096;
    font-style: italic;
}

.border-dashed {
    border-top-style: dashed !important;
    border-top-width: 2px !important;
    border-top-color: #e2e8f0 !important;
}

.animate-fade-up {
    animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
    transform: translateY(30px);
}

.delay-1 {
    animation-delay: 0.15s;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Print Styles */
@media print {
    header,
    nav,
    .result-card,
    .no-print,
    .btn,
    button,
    .chat-widget,
    .chatbot-button,
    iframe {
        display: none !important;
    }

    .result-page {
        padding: 0 !important;
        margin: 0 !important;
        background: white !important;
        display: block !important;
        min-height: auto !important;
    }

    .container {
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .receipt-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 auto !important;
        max-width: 600px !important;
    }

    .receipt-card::before {
        display: none !important;
    }

    .border-dashed {
        border-top-color: #718096 !important;
    }
}

@media (max-width: 991.98px) {
    .result-page {
        padding-top: 90px !important;
    }
    .result-card {
        padding: 2.5rem 1.5rem;
    }
    .receipt-card {
        padding: 2rem 1.5rem;
    }
}

@media (max-width: 575.98px) {
    .result-title {
        font-size: 1.8rem;
    }
}
</style>
