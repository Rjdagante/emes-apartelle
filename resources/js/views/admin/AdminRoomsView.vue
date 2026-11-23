<template>
  <div class="rooms-inventory-view">
    <!-- Header Summary Stats -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stats-card p-4 rounded-4 bg-white shadow-sm border-0 cursor-pointer" @click="activeMainTab = 'all'">
          <div class="d-flex align-items-center gap-3">
            <div class="icon-box bg-primary-gold-subtle text-primary-gold rounded-3">
              <i class="bi bi-door-closed fs-4"></i>
            </div>
            <div>
              <h3 class="fw-bold mb-0 text-secondary-dark">{{ rooms.length }}</h3>
              <p class="text-muted small mb-0">Total Rooms</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stats-card p-4 rounded-4 bg-white shadow-sm border-0 cursor-pointer" @click="activeMainTab = 'all'">
          <div class="d-flex align-items-center gap-3">
            <div class="icon-box bg-success-subtle text-success rounded-3">
              <i class="bi bi-check-circle fs-4"></i>
            </div>
            <div>
              <h3 class="fw-bold mb-0 text-secondary-dark text-success">{{ availableRoomsCount }}</h3>
              <p class="text-muted small mb-0">Available Now</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stats-card p-4 rounded-4 bg-white shadow-sm border-0 cursor-pointer" @click="activeMainTab = 'promo'">
          <div class="d-flex align-items-center gap-3">
            <div class="icon-box bg-warning-subtle text-warning rounded-3">
              <i class="bi bi-tag-fill fs-4"></i>
            </div>
            <div>
              <h3 class="fw-bold mb-0 text-secondary-dark">{{ promoRoomsCount }}</h3>
              <p class="text-muted small mb-0">Promo Rooms</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stats-card p-4 rounded-4 bg-white shadow-sm border-0 cursor-pointer" @click="activeMainTab = 'packages'">
          <div class="d-flex align-items-center gap-3">
            <div class="icon-box bg-info-subtle text-info rounded-3">
              <i class="bi bi-box2-heart-fill fs-4"></i>
            </div>
            <div>
              <h3 class="fw-bold mb-0 text-secondary-dark">{{ packages.length }}</h3>
              <p class="text-muted small mb-0">Room Packages</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden animate-fade-up">
      <div class="card-header bg-white py-3 px-4 border-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <!-- Modern Main Tabs -->
        <div class="d-flex align-items-center gap-2 p-1 bg-light rounded-pill border">
          <button 
            type="button" 
            class="btn btn-sm rounded-pill px-4 py-2 fw-bold transition-all border-0 shadow-none d-flex align-items-center gap-2"
            :class="activeMainTab === 'all' ? 'btn-gold text-white shadow-sm' : 'text-muted'"
            @click="activeMainTab = 'all'"
          >
            <i class="bi bi-grid-fill"></i>
            <span>All Rooms</span>
            <span class="badge rounded-pill" :class="activeMainTab === 'all' ? 'bg-white text-dark' : 'bg-secondary-subtle text-muted'">
              {{ rooms.length }}
            </span>
          </button>
          <button 
            type="button" 
            class="btn btn-sm rounded-pill px-4 py-2 fw-bold transition-all border-0 shadow-none d-flex align-items-center gap-2"
            :class="activeMainTab === 'promo' ? 'btn-gold text-white shadow-sm' : 'text-muted'"
            @click="activeMainTab = 'promo'"
          >
            <i class="bi bi-tag-fill"></i>
            <span>Promo Rooms</span>
            <span class="badge rounded-pill" :class="activeMainTab === 'promo' ? 'bg-white text-dark' : 'bg-warning text-dark'">
              {{ promoRoomsCount }}
            </span>
          </button>
          <button 
            type="button" 
            class="btn btn-sm rounded-pill px-4 py-2 fw-bold transition-all border-0 shadow-none d-flex align-items-center gap-2"
            :class="activeMainTab === 'packages' ? 'btn-gold text-white shadow-sm' : 'text-muted'"
            @click="activeMainTab = 'packages'"
          >
            <i class="bi bi-box2-heart-fill"></i>
            <span>Room Packages</span>
            <span class="badge rounded-pill" :class="activeMainTab === 'packages' ? 'bg-white text-dark' : 'bg-info text-white'">
              {{ packages.length }}
            </span>
          </button>
        </div>

        <!-- Search and Action Buttons -->
        <div class="d-flex gap-2">
           <div class="input-group input-group-sm" style="width: 260px;">
             <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
             <input type="text" class="form-control bg-light border-0" :placeholder="activeMainTab === 'packages' ? 'Search packages...' : (activeMainTab === 'promo' ? 'Search promo rooms...' : 'Search Room # or Type...')" v-model="searchQuery">
           </div>
           
           <button 
             v-if="activeMainTab === 'packages'" 
             class="btn btn-gold px-4 d-flex align-items-center gap-2 rounded-pill shadow-sm py-2" 
             @click="openAddPackageModal"
           >
             <i class="bi bi-plus-circle fw-bold"></i> 
             <span class="fw-bold text-uppercase small tracking-wider">Add Package</span>
           </button>
           <button 
             v-else-if="activeMainTab === 'promo'" 
             class="btn btn-gold px-4 d-flex align-items-center gap-2 rounded-pill shadow-sm py-2" 
             @click="openAddPromoModal"
           >
             <i class="bi bi-plus-circle fw-bold"></i> 
             <span class="fw-bold text-uppercase small tracking-wider">Add Promo Room</span>
           </button>
           <button 
             v-else 
             class="btn btn-gold px-4 d-flex align-items-center gap-2 rounded-pill shadow-sm py-2" 
             @click="openAddModal"
           >
             <i class="bi bi-plus-lg fw-bold"></i> 
             <span class="fw-bold text-uppercase small tracking-wider">Add Room</span>
           </button>
        </div>
      </div>

      <div class="card-body p-0">
        <!-- TAB 1: ALL ROOMS TABLE -->
        <div class="table-responsive" v-if="activeMainTab === 'all'">
          <table class="table table-hover align-middle mb-0">
            <thead class="bg-light border-bottom">
              <tr>
                <th class="ps-4 py-3 text-muted small fw-bold text-uppercase tracking-wider">Room Details</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Type & Size</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Price / Night</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Status</th>
                <th class="pe-4 py-3 text-muted small fw-bold text-uppercase tracking-wider text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="py-5 text-center">
                  <div class="spinner-border text-gold" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="text-muted mt-2">Loading rooms...</p>
                </td>
              </tr>
              <tr v-for="room in paginatedRooms" :key="room.id" v-else>
                <td class="ps-4 py-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="room-thumb rounded-3 overflow-hidden shadow-sm position-relative" style="width: 70px; height: 50px;">
                      <img :src="getRoomDisplayImage(room)" @error="onImageError($event, room.room_type)" class="w-100 h-100 object-fit-cover">
                      <div v-if="room.is_promo" class="position-absolute top-0 start-0 bg-gold text-white xx-small px-1 fw-bold">
                        PROMO
                      </div>
                    </div>
                    <div>
                      <span class="fw-bold text-secondary-dark d-block">Room #{{ room.room_number }}</span>
                      <small class="text-muted"><i class="bi bi-people me-1"></i>Max {{ room.max_occupancy }} Guests</small>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="d-block fw-semibold text-dark">{{ room.room_type }}</span>
                  <small class="text-muted" v-if="room.room_size"><i class="bi bi-arrows-fullscreen me-1 small"></i>{{ room.room_size }} sq.m</small>
                </td>
                <td>
                  <div v-if="room.is_promo" class="d-flex flex-column">
                    <div class="d-flex align-items-center gap-1">
                      <small class="text-muted text-decoration-line-through">
                        ₱{{ formatPrice(isPerHeadRoom(room) ? room.price_per_head : room.price_per_night) }}
                      </small>
                      <span class="badge bg-gold-subtle text-gold x-small px-2 py-0.5 rounded-pill">
                        {{ room.promo_discount_percent ? `${room.promo_discount_percent}% OFF` : 'PROMO' }}
                      </span>
                    </div>
                    <span class="fw-bold text-gold fs-5">
                      ₱{{ formatPrice(isPerHeadRoom(room) ? (room.promo_price_per_head || room.price_per_head) : (room.promo_price || room.price_per_night)) }}
                      <small class="text-muted small fw-normal">{{ isPerHeadRoom(room) ? '/ head' : '' }}</small>
                    </span>
                  </div>
                  <div v-else>
                    <span class="fw-bold text-gold fs-5" v-if="room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room'">
                      ₱{{ formatPrice(room.price_per_head) }} / head
                    </span>
                    <span class="fw-bold text-gold fs-5" v-else>
                      ₱{{ formatPrice(room.price_per_night) }}
                    </span>
                  </div>
                </td>
                <td>
                  <span class="badge rounded-pill fw-bold text-uppercase px-3 py-2" :class="statusBadgeClass(room.status)" style="font-size: 0.7rem;">
                    {{ room.status }}
                  </span>
                </td>
                <td class="pe-4 text-end">
                  <div class="btn-group gap-2">
                    <button class="btn btn-icon btn-light" @click="viewRoom(room)" title="View Room">
                      <i class="bi bi-eye text-secondary-dark"></i>
                    </button>
                    <button 
                      class="btn btn-icon" 
                      :class="room.is_promo ? 'btn-gold text-white shadow-sm' : 'btn-light-gold'" 
                      @click="openPromoModalForRoom(room)" 
                      :title="room.is_promo ? 'Edit Promotion' : 'Set as Promo Room'"
                    >
                      <i class="bi bi-tag-fill"></i>
                    </button>
                    <button 
                      class="btn btn-icon btn-light-info" 
                      @click="openAddPackageModalForRoom(room)" 
                      title="Add Package for this Room"
                    >
                      <i class="bi bi-box2-heart"></i>
                    </button>
                    <button class="btn btn-icon btn-light-gold" @click="openEditModal(room)" title="Edit Room">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-icon btn-light-danger" @click="confirmDelete(room.id)" title="Delete Room">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredRooms.length === 0 && !loading">
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="bi bi-inbox fs-1 opacity-25 d-block mb-3"></i>
                  No rooms found matching your search.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- TAB 2: PROMO ROOMS TABLE -->
        <div class="table-responsive" v-else-if="activeMainTab === 'promo'">
          <table class="table table-hover align-middle mb-0">
            <thead class="bg-light border-bottom">
              <tr>
                <th class="ps-4 py-3 text-muted small fw-bold text-uppercase tracking-wider">Promo Room</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Regular Price</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Promo Price & Savings</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Campaign Title</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Status</th>
                <th class="pe-4 py-3 text-muted small fw-bold text-uppercase tracking-wider text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="py-5 text-center">
                  <div class="spinner-border text-gold" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="text-muted mt-2">Loading promo rooms...</p>
                </td>
              </tr>
              <tr v-for="room in paginatedPromoRooms" :key="room.id" v-else>
                <td class="ps-4 py-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="room-thumb rounded-3 overflow-hidden shadow-sm position-relative" style="width: 70px; height: 50px;">
                      <img :src="getRoomDisplayImage(room)" @error="onImageError($event, room.room_type)" class="w-100 h-100 object-fit-cover">
                      <div class="position-absolute top-0 start-0 bg-gold text-white xx-small px-1 fw-bold">PROMO</div>
                    </div>
                    <div>
                      <span class="fw-bold text-secondary-dark d-block">Room #{{ room.room_number }}</span>
                      <small class="text-muted">{{ room.room_type }} &bull; Max {{ room.max_occupancy }} Guests</small>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="text-muted text-decoration-line-through fw-semibold">
                    ₱{{ formatPrice(isPerHeadRoom(room) ? room.price_per_head : room.price_per_night) }}
                  </span>
                  <small class="text-muted d-block x-small">{{ isPerHeadRoom(room) ? 'per head / night' : 'per night' }}</small>
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <span class="fw-bold text-gold fs-5">
                      ₱{{ formatPrice(isPerHeadRoom(room) ? (room.promo_price_per_head || room.price_per_head) : (room.promo_price || room.price_per_night)) }}
                      <small class="text-muted small fw-normal">{{ isPerHeadRoom(room) ? '/ head' : '' }}</small>
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill x-small px-2 py-0.5 mt-1 d-inline-block text-start" style="width: fit-content;">
                      Save ₱{{ formatPrice(getRoomSavings(room)) }} ({{ getRoomDiscountPercent(room) }}% OFF)
                    </span>
                  </div>
                </td>
                <td>
                  <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-2 rounded-pill small fw-bold">
                    <i class="bi bi-tag-fill text-gold me-1"></i>
                    {{ room.promo_title || 'Special Promotion' }}
                  </span>
                  <small v-if="room.promo_description" class="text-muted d-block x-small mt-1 text-truncate" style="max-width: 220px;">
                    {{ room.promo_description }}
                  </small>
                </td>
                <td>
                  <span class="badge rounded-pill px-3 py-2 fw-bold text-uppercase" :class="room.is_promo ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-muted'" style="font-size: 0.7rem;">
                    <i class="bi bi-check-circle-fill me-1" v-if="room.is_promo"></i>
                    {{ room.is_promo ? 'Active Promo' : 'Paused' }}
                  </span>
                </td>
                <td class="pe-4 text-end">
                  <div class="btn-group gap-2">
                    <button class="btn btn-icon btn-light-gold" @click="openPromoModalForRoom(room)" title="Edit Deal">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-icon btn-light-danger" @click="removePromoFromRoom(room)" title="End Promo">
                      <i class="bi bi-x-circle"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredPromoRooms.length === 0 && !loading">
                <td colspan="6" class="text-center py-5">
                  <div class="py-4">
                    <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex p-3 mb-3">
                      <i class="bi bi-tag fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-secondary-dark">No Promotional Rooms</h5>
                    <p class="text-muted small mb-3">Launch a promotional deal or discount for any room to attract more guests.</p>
                    <button class="btn btn-gold rounded-pill px-4 py-2 fw-bold small text-uppercase shadow-sm" @click="openAddPromoModal">
                      <i class="bi bi-plus-circle me-1"></i> Add Promo Room
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- TAB 3: ROOM PACKAGES TABLE -->
        <div class="table-responsive" v-else-if="activeMainTab === 'packages'">
          <table class="table table-hover align-middle mb-0">
            <thead class="bg-light border-bottom">
              <tr>
                <th class="ps-4 py-3 text-muted small fw-bold text-uppercase tracking-wider">Package Details</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Accommodation</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Duration & Pax</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Package Rate</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Inclusions Preview</th>
                <th class="py-3 text-muted small fw-bold text-uppercase tracking-wider">Status</th>
                <th class="pe-4 py-3 text-muted small fw-bold text-uppercase tracking-wider text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="packagesLoading">
                <td colspan="7" class="py-5 text-center">
                  <div class="spinner-border text-gold" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                  <p class="text-muted mt-2">Loading room packages...</p>
                </td>
              </tr>
              <tr v-for="pkg in paginatedPackages" :key="pkg.id" v-else>
                <td class="ps-4 py-3">
                  <div class="d-flex align-items-center gap-3">
                    <div class="room-thumb rounded-3 overflow-hidden shadow-sm position-relative" style="width: 75px; height: 55px;">
                      <img :src="getPackageDisplayImage(pkg)" @error="onPkgImageError($event)" class="w-100 h-100 object-fit-cover">
                      <div class="position-absolute top-0 start-0 bg-dark text-white xx-small px-1.5 py-0.5 fw-bold" style="font-size: 0.65rem;">
                        PACKAGE
                      </div>
                    </div>
                    <div>
                      <span class="fw-bold text-secondary-dark d-block">{{ pkg.name }}</span>
                      <small class="text-muted d-block text-truncate" style="max-width: 200px;" v-if="pkg.tagline">
                        {{ pkg.tagline }}
                      </small>
                    </div>
                  </div>
                </td>
                <td>
                  <div v-if="pkg.room">
                    <span class="badge bg-gold-subtle text-dark border border-gold-subtle px-2 py-1 rounded-pill small fw-bold">
                      <i class="bi bi-door-closed me-1"></i>Room #{{ pkg.room.room_number }} ({{ pkg.room.room_type }})
                    </span>
                  </div>
                  <div v-else>
                    <span class="badge bg-light text-secondary-dark border px-2 py-1 rounded-pill small fw-semibold">
                      <i class="bi bi-house-door me-1"></i>{{ pkg.room_type_label || 'Private Room' }}
                    </span>
                  </div>
                  <small class="text-muted d-block mt-1 x-small" v-if="pkg.tour_type">
                    <i class="bi bi-compass me-1"></i>{{ pkg.tour_type }}
                  </small>
                </td>
                <td>
                  <span class="fw-bold text-dark d-block">{{ pkg.duration || '3D, 2N' }}</span>
                  <small class="text-muted"><i class="bi bi-people me-1"></i>{{ pkg.pax || 'Good for 2 pax' }}</small>
                </td>
                <td>
                  <span class="fw-bold text-gold fs-5">₱{{ formatPrice(pkg.price) }}</span>
                  <small class="text-muted d-block x-small">total package</small>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                    <template v-if="pkg.room?.amenities && pkg.room.amenities.length > 0">
                      <span 
                        v-for="amenity in pkg.room.amenities.slice(0, 2)" 
                        :key="'ra-'+amenity.id" 
                        class="badge bg-light text-dark border rounded-pill x-small px-2 py-1"
                      >
                        <i class="bi bi-check2 text-success me-1"></i>{{ amenity.name }}
                      </span>
                    </template>
                    <template v-else-if="pkg.room_inclusions && pkg.room_inclusions.length > 0">
                      <span 
                        v-for="(inc, idx) in pkg.room_inclusions.slice(0, 2)" 
                        :key="'r-'+idx" 
                        class="badge bg-light text-dark border rounded-pill x-small px-2 py-1"
                      >
                        <i class="bi bi-check2 text-success me-1"></i>{{ inc }}
                      </span>
                    </template>
                    <span 
                      v-for="(dest, idx) in (pkg.tour_destinations || []).slice(0, 2)" 
                      :key="'d-'+idx" 
                      class="badge bg-info-subtle text-info border border-info-subtle rounded-pill x-small px-2 py-1"
                    >
                      🏝️ {{ dest }}
                    </span>
                    <span 
                      v-if="(((pkg.room?.amenities?.length || pkg.room_inclusions?.length || 0)) + (pkg.tour_destinations?.length || 0)) > 4" 
                      class="badge bg-secondary-subtle text-muted rounded-pill x-small px-2 py-1"
                    >
                      +{{ (((pkg.room?.amenities?.length || pkg.room_inclusions?.length || 0)) + (pkg.tour_destinations?.length || 0)) - 4 }} more
                    </span>
                  </div>
                </td>
                <td>
                  <button 
                    type="button"
                    class="badge rounded-pill px-3 py-2 fw-bold text-uppercase border-0 transition-all cursor-pointer" 
                    :class="pkg.is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-muted'" 
                    style="font-size: 0.7rem;"
                    @click="togglePackageStatus(pkg)"
                    title="Click to toggle Active / Paused status"
                  >
                    <i class="bi bi-check-circle-fill me-1" v-if="pkg.is_active"></i>
                    <i class="bi bi-pause-circle me-1" v-else></i>
                    {{ pkg.is_active ? 'Active' : 'Paused' }}
                  </button>
                </td>
                <td class="pe-4 text-end">
                  <div class="btn-group gap-2">
                    <button class="btn btn-icon btn-light-info" @click="viewPackageDetails(pkg)" title="View Package Details">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-icon btn-light-gold" @click="openEditPackageModal(pkg)" title="Edit Package">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-icon btn-light-danger" @click="confirmDeletePackage(pkg.id)" title="Delete Package">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredPackages.length === 0 && !packagesLoading">
                <td colspan="7" class="text-center py-5">
                  <div class="py-4">
                    <div class="rounded-circle bg-info-subtle text-info d-inline-flex p-3 mb-3">
                      <i class="bi bi-box2-heart fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-secondary-dark">No Room Packages Found</h5>
                    <p class="text-muted small mb-3">Create special package deals (like 3D,2N Island Hopping + Private Room accommodation).</p>
                    <button class="btn btn-gold rounded-pill px-4 py-2 fw-bold small text-uppercase shadow-sm" @click="openAddPackageModal">
                      <i class="bi bi-plus-circle me-1"></i> Add Package
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <AdminPagination 
          :current-page="activeMainTab === 'packages' ? currentPackagePage : (activeMainTab === 'promo' ? currentPromoPage : currentPage)" 
          :total-items="activeMainTab === 'packages' ? filteredPackages.length : (activeMainTab === 'promo' ? filteredPromoRooms.length : filteredRooms.length)" 
          :page-size="pageSize"
          @change="page => { 
            if (activeMainTab === 'packages') currentPackagePage = page; 
            else if (activeMainTab === 'promo') currentPromoPage = page; 
            else currentPage = page; 
          }"
          @page-size-change="size => { 
            pageSize = size; 
            currentPage = 1; 
            currentPromoPage = 1; 
            currentPackagePage = 1; 
          }"
        />
      </div>
    </div>

    <!-- Advanced UI/UX Modular Modal -->
    <div v-if="showModal" class="modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in">
      <div class="modal-card-premium bg-white rounded-5 shadow-2xl w-100 d-flex flex-column overflow-hidden my-auto" style="max-width: 1000px; height: 90vh; max-height: 850px;">
        
        <!-- Premium Modal Header -->
        <div class="modal-header-premium p-4 d-flex justify-content-between align-items-center border-bottom bg-white-glass">
          <div class="d-flex align-items-center gap-3">
             <div class="brand-icon-box bg-gold text-white rounded-4 d-flex align-items-center justify-content-center shadow-gold">
               <i class="bi bi-house-add-fill fs-4" v-if="!editingRoom"></i>
               <i class="bi bi-pencil-square fs-4" v-else></i>
             </div>
             <div>
               <h4 class="serif-font fw-bold mb-0 text-dark">{{ editingRoom ? 'Edit Room' : 'Add Room' }}</h4>
               <p class="text-muted small mb-0">{{ editingRoom ? 'Edit Room #' + editingRoom.room_number : 'Add a new room.' }}</p>
             </div>
          </div>
          <button class="btn-close-premium" @click="closeModal">
            <i class="bi bi-xl"></i>
          </button>
        </div>

        <!-- Modal Body with Layout Sidebar & Content -->
        <div class="modal-body-premium d-flex flex-grow-1 overflow-hidden">
          
          <!-- Modal Sidebar Tabs -->
          <div class="modal-sidebar bg-cream-subtle p-3 border-end d-none d-md-block" style="width: 220px;">
            <ul class="nav flex-column gap-2 list-unstyled">
              <li v-for="tab in formTabs" :key="tab.id">
                <button @click="activeTab = tab.id" 
                  class="nav-tab-btn py-3 px-3 rounded-4 w-100 d-flex align-items-center gap-3 transition-all"
                  :class="{'active-tab shadow-sm': activeTab === tab.id}">
                  <i :class="['bi', tab.icon, 'fs-5']"></i>
                  <span class="fw-bold small text-uppercase tracking-wider">{{ tab.name }}</span>
                </button>
              </li>
            </ul>

            <!-- Visual Preview Card Shortcut (Bottom of Sibebar) -->
            <div class="mt-auto p-3 bg-white rounded-4 border border-dashed border-gold-light opacity-75">
               <div class="small fw-bold text-gold text-uppercase mb-2 text-center" style="font-size: 0.6rem;">Room Preview</div>
               <div class="d-flex flex-column align-items-center">
                  <span class="h4 mb-0 fw-bold serif-font text-dark">{{ form.room_number || '---' }}</span>
                  <span class="small text-muted">{{ form.room_type.split(' ')[0] }}</span>
               </div>
            </div>
          </div>

          <!-- Main Form Content -->
          <div class="flex-grow-1 p-4 p-lg-5 overflow-y-auto bg-white custom-scrollbar">
            <form id="roomPremiumForm" @submit.prevent="saveRoom" novalidate>
              
              <!-- TAB 1: BASIC INFORMATION -->
              <div v-show="activeTab === 'basic'" class="animate-tab-content">
                 <div class="section-title-premium mb-4">
                    <span class="badge bg-gold-subtle text-gold mb-2">Step 1</span>
                    <h5 class="fw-bold text-dark">Room Info</h5>
                    <p class="text-muted small">Enter the room number and type.</p>
                 </div>

                 <div class="row g-4">
                    <div class="col-md-6">
                       <div class="floating-group">
                           <input type="text" v-model="form.room_number" class="input-modern-xl" placeholder=" " required id="f_room_no">
                           <label for="f_room_no">Room Number</label>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group">
                          <select v-model="form.room_type" class="input-modern-xl" required id="f_room_type">
                             <option value="Family Room">Family Room</option>
                             <option value="Barkadahan Room">Barkadahan Room</option>
                             <option value="Couple Room">Couple Room</option>
                          </select>
                          <label for="f_room_type">Room Type</label>
                       </div>
                    </div>
                    <div class="col-md-12">
                       <div class="floating-group">
                           <textarea v-model="form.description" class="input-modern-xl" rows="4" placeholder=" " id="f_desc"></textarea>
                           <label for="f_desc">Description</label>
                       </div>
                    </div>
                 </div>
              </div>

              <!-- TAB 2: PRICING & SPECS -->
              <div v-show="activeTab === 'specs'" class="animate-tab-content">
                 <div class="section-title-premium mb-4">
                    <span class="badge bg-gold-subtle text-gold mb-2">Step 2</span>
                    <h5 class="fw-bold text-dark">Price & Size</h5>
                    <p class="text-muted small">Set price, guest count, bed type, and size.</p>
                 </div>

                 <div class="row g-4">
                    <div class="col-md-6" v-if="form.room_type !== 'Family Room' && form.room_type !== 'Barkadahan Room'">
                       <div class="floating-group price-field">
                          <input type="number" v-model="form.price_per_night" class="input-modern-xl" placeholder=" " :required="form.room_type !== 'Family Room' && form.room_type !== 'Barkadahan Room'" id="f_price">
                          <label for="f_price">Price per Night (PHP)</label>
                          <span class="currency-prefix">₱</span>
                       </div>
                    </div>
                    <div class="col-md-6" v-if="form.room_type === 'Family Room' || form.room_type === 'Barkadahan Room'">
                       <div class="floating-group price-field">
                          <input type="number" v-model="form.price_per_head" class="input-modern-xl" placeholder=" " id="f_price_head">
                          <label for="f_price_head">Per Head per Night (PHP)</label>
                          <span class="currency-prefix">₱</span>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group">
                          <input type="number" v-model="form.min_occupancy" class="input-modern-xl" placeholder=" " required id="f_min">
                          <label for="f_min">Min Guests</label>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group">
                          <input type="number" v-model="form.max_occupancy" class="input-modern-xl" placeholder=" " required id="f_max">
                          <label for="f_max">Max Guests</label>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group price-field">
                          <input type="number" v-model="form.extra_bed_price" class="input-modern-xl" placeholder=" " id="f_extra_bed">
                          <label for="f_extra_bed">Extra Bedding Fee / Night (PHP)</label>
                          <span class="currency-prefix">₱</span>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group">
                          <input type="number" v-model="form.max_extra_guests" class="input-modern-xl" placeholder=" " min="0" max="10" id="f_max_extra">
                          <label for="f_max_extra">Max Extra Beddings / Guests</label>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group">
                          <input type="text" v-model="form.bed_type" class="input-modern-xl" placeholder=" " id="f_bed">
                          <label for="f_bed">Bed Type</label>
                       </div>
                    </div>
                    <div class="col-md-6">
                       <div class="floating-group">
                          <input type="text" v-model="form.room_size" class="input-modern-xl" placeholder=" " id="f_size">
                          <label for="f_size">Room Size (sq.m)</label>
                       </div>
                    </div>
                    <div class="col-md-12">
                        <label class="small fw-bold text-uppercase text-muted mb-3 d-block tracking-widest">Status</label>
                        <div class="status-selector-modern d-flex gap-3">
                           <div class="status-option flex-grow-1">
                              <input type="radio" class="btn-check" name="room_status" id="s_avail" value="available" v-model="form.status">
                              <label class="btn btn-status-modern w-100 py-3" for="s_avail">
                                 <i class="bi bi-check-circle-fill me-2"></i> Available
                              </label>
                           </div>
                           <div class="status-option flex-grow-1">
                               <input type="radio" class="btn-check" name="room_status" id="s_maint" value="maintenance" v-model="form.status">
                               <label class="btn btn-status-modern maint w-100 py-3" for="s_maint">
                                  <i class="bi bi-tools me-2"></i> Maintenance
                               </label>
                            </div>
                        </div>
                    </div>

                    <!-- Promotional Settings Section -->
                    <div class="col-md-12">
                      <div class="p-4 rounded-4 bg-light border border-dashed border-gold-light">
                        <div class="form-check form-switch d-flex align-items-center gap-3 mb-0">
                          <input class="form-check-input cursor-pointer" type="checkbox" id="f_is_promo" v-model="form.is_promo" style="width: 2.5em; height: 1.3em;">
                          <label class="form-check-label fw-bold text-secondary-dark cursor-pointer mb-0" for="f_is_promo">
                            <i class="bi bi-tag-fill text-gold me-1"></i> Promotional Discount / Special Offer
                          </label>
                        </div>

                        <div v-if="form.is_promo" class="row g-3 mt-2 animate-fade-in">
                          <div class="col-md-6">
                            <div class="floating-group">
                              <input type="text" v-model="form.promo_title" class="input-modern-xl" placeholder=" " id="f_promo_title">
                              <label for="f_promo_title">Promo Badge / Title (e.g. 20% OFF)</label>
                            </div>
                          </div>
                          <div class="col-md-6" v-if="form.room_type === 'Family Room' || form.room_type === 'Barkadahan Room'">
                            <div class="floating-group price-field">
                              <input type="number" v-model="form.promo_price_per_head" class="input-modern-xl" placeholder=" " id="f_promo_price_head">
                              <label for="f_promo_price_head">Promo Price per Head (PHP)</label>
                              <span class="currency-prefix">₱</span>
                            </div>
                          </div>
                          <div class="col-md-6" v-else>
                            <div class="floating-group price-field">
                              <input type="number" v-model="form.promo_price" class="input-modern-xl" placeholder=" " id="f_promo_price">
                              <label for="f_promo_price">Promo Price per Night (PHP)</label>
                              <span class="currency-prefix">₱</span>
                            </div>
                          </div>
                          <div class="col-12">
                            <div class="floating-group">
                              <input type="text" v-model="form.promo_description" class="input-modern-xl" placeholder=" " id="f_promo_desc">
                              <label for="f_promo_desc">Promo Description (Optional)</label>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                 </div>
              </div>

              <!-- TAB 3: MEDIA & AMENITIES -->
              <div v-show="activeTab === 'media'" class="animate-tab-content">
                 <div class="section-title-premium mb-4">
                    <span class="badge bg-gold-subtle text-gold mb-2">Step 3</span>
                    <h5 class="fw-bold text-dark">Photos & Amenities</h5>
                    <p class="text-muted small">Add photos and select amenities.</p>
                 </div>

                  <div class="row g-4">
                    <div class="col-12">
                       <label class="small fw-bold text-uppercase text-muted mb-3 d-block tracking-widest">Main Photo</label>
                       <div class="image-preview-slot rounded-5 border-2 border-dashed border-gold-light mb-4 overflow-hidden position-relative group cursor-pointer" @click="$refs.fileInput.click()">
                          <img v-if="imagePreview" :src="imagePreview" class="w-100 h-100 object-fit-cover">
                          <img v-else-if="form.image" :src="form.image" class="w-100 h-100 object-fit-cover">
                          <div v-else class="h-100 d-flex flex-column align-items-center justify-content-center text-muted py-5">
                             <i class="bi bi-cloud-arrow-up fs-1 opacity-25 mb-2"></i>
                             <span class="small fw-semibold">Click to upload main photo</span>
                          </div>
                          
                          <!-- Remove Main Image Button -->
                          <button v-if="imagePreview || form.image" type="button" @click.stop="removeMainImage" class="btn-remove-img shadow-sm position-absolute top-0 end-0 m-3 z-3" title="Remove Main Image">
                             <i class="bi bi-x-lg"></i>
                          </button>

                          <div class="preview-overlay position-absolute inset-0 bg-dark bg-opacity-25 d-flex align-items-center justify-content-center opacity-0 group-hover-opacity-100 transition-all">
                             <button type="button" class="btn btn-light btn-sm rounded-pill px-3">
                                <i class="bi bi-camera-fill me-2"></i> Change Main Photo
                             </button>
                          </div>
                       </div>
                       <input type="file" ref="fileInput" class="d-none" @change="handleFileChange" accept="image/*">
                    </div>

                    <!-- Additional Images Section -->
                    <div class="col-12 mt-2">
                       <label class="small fw-bold text-uppercase text-muted mb-3 d-block tracking-widest d-flex justify-content-between">
                          More Photos
                          <span class="text-gold">{{ existingImages.length + multipleImagePreviews.length }} Total</span>
                       </label>
                       
                       <div class="gallery-upload-grid d-grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                          <!-- Existing Images -->
                          <div v-for="img in existingImages" :key="img.id" class="gallery-item-premium rounded-4 overflow-hidden border position-relative shadow-sm aspect-square">
                             <img :src="img.image_path" @error="onImageError($event, form.room_type)" class="w-100 h-100 object-fit-cover">
                             <button type="button" @click="removeExistingImage(img.id)" class="btn-remove-img shadow-sm position-absolute top-0 end-0 m-1">
                                <i class="bi bi-x"></i>
                             </button>
                          </div>

                          <!-- New Upload Previews -->
                          <div v-for="(preview, idx) in multipleImagePreviews" :key="idx" class="gallery-item-premium rounded-4 overflow-hidden border border-gold-subtle position-relative shadow-sm aspect-square animate-fade-in">
                             <img :src="preview" class="w-100 h-100 object-fit-cover">
                             <div class="new-badge position-absolute top-0 start-0 bg-gold text-white x-small px-2 py-0">NEW</div>
                             <button type="button" @click="removeNewImage(idx)" class="btn-remove-img shadow-sm position-absolute top-0 end-0 m-1">
                                <i class="bi bi-x"></i>
                             </button>
                          </div>

                          <!-- Add Button -->
                          <div class="gallery-add-card rounded-4 border-2 border-dashed border-gold-light d-flex flex-column align-items-center justify-content-center cursor-pointer hover-bg-light transition-all aspect-square" @click="$refs.multipleFileInput.click()">
                             <i class="bi bi-plus-circle text-gold fs-3 mb-1"></i>
                             <span class="x-small fw-bold text-muted text-uppercase">Add more</span>
                          </div>
                       </div>
                       <input type="file" ref="multipleFileInput" class="d-none" @change="handleMultipleFilesChange" accept="image/*" multiple>
                    </div>

                    <div class="col-12 mt-5">
                       <label class="small fw-bold text-uppercase text-muted mb-4 d-block tracking-widest d-flex justify-content-between">
                          Amenities
                          <span class="text-gold">{{ form.amenities.length }} Selected</span>
                       </label>
                       <div class="row g-3">
                          <div v-for="amenity in allAmenities" :key="amenity.id" class="col-lg-4 col-sm-6">
                             <div class="amenity-checkbox-modern position-relative" :class="{'selected': form.amenities.includes(amenity.id)}">
                                 <input type="checkbox" :id="'am-'+amenity.id" :value="amenity.id" v-model="form.amenities" class="d-none">
                                 <label :for="'am-'+amenity.id" class="w-100 h-100 p-3 rounded-4 border transition-all d-flex align-items-center gap-3">
                                    <div class="amenity-icon-mini shadow-sm rounded-circle d-flex align-items-center justify-content-center">
                                       <i :class="['bi', formatIconClass(amenity.icon)]"></i>
                                    </div>
                                    <div class="overflow-hidden">
                                       <span class="d-block fw-bold small text-truncate">{{ amenity.name }}</span>
                                    </div>
                                    <div class="ms-auto selected-dot shadow-sm"></div>
                                 </label>
                             </div>
                          </div>
                       </div>
                    </div>
                 </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Premium Modal Footer -->
        <div class="modal-footer-premium p-4 border-top bg-white d-flex justify-content-between align-items-center">
           <div class="d-flex gap-2 align-items-center overflow-hidden">
              <div class="progress-steps d-flex gap-2">
                 <div v-for="tab in formTabs" :key="tab.id" class="step-dot" :class="{'active-dot': activeTab === tab.id, 'done-dot': isTabFilled(tab.id)}"></div>
              </div>
              <span class="ms-2 small text-muted d-none d-sm-block">{{ activeTabLabel }}</span>
           </div>
           
           <div class="d-flex gap-3">
              <button type="button" class="btn btn-outline-modern px-4 py-2 fw-semibold rounded-pill" @click="closeModal">Cancel</button>
              
              <button v-if="activeTab !== 'media'" @click="activeTab = getNextTab()" type="button" class="btn btn-dark-modern px-4 py-2 rounded-pill d-flex align-items-center gap-2">
                 <span>Next</span>
                 <i class="bi bi-arrow-right"></i>
              </button>
              
              <button v-else type="button" @click="saveRoom" class="btn btn-gold px-5 py-2 rounded-pill shadow-gold d-flex align-items-center gap-2" :disabled="saving">
                 <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                 <span class="fw-bold text-uppercase tracking-wider small">Save</span>
              </button>
           </div>
        </div>

      </div>
    </div>

    <!-- View Room Details Modal -->
    <div v-if="showViewModal" class="modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in">
      <div class="modal-card-premium bg-white rounded-5 shadow-2xl w-100 d-flex flex-column overflow-hidden my-auto" style="max-width: 800px; max-height: 90vh;">
        <div class="modal-header-premium p-4 d-flex justify-content-between align-items-center border-bottom bg-white-glass">
          <div class="d-flex align-items-center gap-3">
             <div class="brand-icon-box bg-gold text-white rounded-circle d-flex align-items-center justify-content-center shadow-gold">
               <i class="bi bi-door-open fs-4"></i>
             </div>
             <div>
               <h4 class="serif-font fw-bold mb-0 text-dark">Room #{{ selectedRoom.room_number }}</h4>
               <p class="text-muted small mb-0">{{ selectedRoom.room_type }} Details</p>
             </div>
          </div>
          <button @click="showViewModal = false" class="btn-close shadow-none"></button>
        </div>
        <div class="modal-body-premium p-4 p-lg-5 overflow-y-auto">
          <div class="row g-4">
            <div class="col-md-5">
              <div class="rounded-4 overflow-hidden shadow-sm mb-4">
                <img :src="getRoomDisplayImage(selectedRoom)" @error="onImageError($event, selectedRoom.room_type)" class="w-100 object-fit-cover" style="height: 250px;">
              </div>
              
              <!-- Additional Images in View Modal -->
              <div v-if="selectedRoom.images && selectedRoom.images.length > 0" class="mb-4">
                 <h6 class="x-small fw-bold text-uppercase text-muted mb-2 letter-spacing-wide">Gallery</h6>
                 <div class="d-flex gap-2 overflow-x-auto pb-2 custom-scrollbar">
                    <div v-for="img in selectedRoom.images" :key="img.id" class="rounded-3 overflow-hidden flex-shrink-0" style="width: 80px; height: 60px;">
                       <img :src="img.image_path" @error="onImageError($event, selectedRoom.room_type)" class="w-100 h-100 object-fit-cover border">
                    </div>
                 </div>
              </div>

              <div class="status-indicator p-3 rounded-4" :class="statusBadgeClass(selectedRoom.status)">
                <div class="small fw-bold text-uppercase mb-1 opacity-75">Current Status</div>
                <div class="h5 mb-0 fw-bold">{{ selectedRoom.status.toUpperCase() }}</div>
              </div>
            </div>
            <div class="col-md-7">
               <div class="mb-4">
                 <h6 class="text-gold small fw-bold text-uppercase mb-2">Room Description</h6>
                 <p class="text-secondary-dark mb-0">{{ selectedRoom.description || 'No description provided.' }}</p>
               </div>
               
               <div class="row g-3">
                  <div class="col-6" v-if="selectedRoom.room_type !== 'Family Room' && selectedRoom.room_type !== 'Barkadahan Room'">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Nightly Rate</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark">₱{{ formatPrice(selectedRoom.price_per_night) }}</span>
                    </div>
                  </div>
                  <div class="col-6" v-if="selectedRoom.room_type === 'Family Room' || selectedRoom.room_type === 'Barkadahan Room'">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Per Head Rate</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark">₱{{ formatPrice(selectedRoom.price_per_head) }}</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Min Guests</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark">{{ selectedRoom.min_occupancy }} Guests</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Max Occupancy</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark">
                        {{ selectedRoom.max_occupancy }} Guests
                        <small v-if="selectedRoom.max_extra_guests > 0" class="text-muted fw-normal d-block x-small">
                          (+{{ selectedRoom.max_extra_guests }} extra bedding)
                        </small>
                      </span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Extra Bedding Fee</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark">₱{{ formatPrice(selectedRoom.extra_bed_price || 300) }} / head</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Room Size</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark">{{ selectedRoom.room_size || '20' }} sq.m</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="p-3 bg-light rounded-3">
                      <small class="text-muted d-block mb-1">Bed Configuration</small>
                      <span class="fw-bold h5 mb-0 text-secondary-dark text-truncate d-block">{{ selectedRoom.bed_type || 'Queen Size' }}</span>
                    </div>
                  </div>
               </div>

               <div class="mt-4">
                 <h6 class="text-gold-dark small fw-bold text-uppercase mb-3 letter-spacing-wide">Inclusive Features</h6>
                 <div class="d-flex flex-wrap gap-2">
                    <div v-for="amenity in selectedRoom.amenities" :key="amenity.id" class="feature-badge d-flex align-items-center gap-2 px-3 py-2 rounded-3 shadow-sm border">
                      <div class="feature-icon bg-gold-subtle text-gold rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                        <i :class="['bi', formatIconClass(amenity.icon), 'small']"></i>
                      </div>
                      <span class="fw-bold text-secondary-dark mb-0 ls-tight" style="font-size: 0.85rem;">{{ amenity.name }}</span>
                    </div>
                    <div v-if="!selectedRoom.amenities?.length" class="text-muted small italic p-2 bg-light rounded-3 w-100 border border-dashed">
                      <i class="bi bi-info-circle me-2"></i>No features listed for this room.
                    </div>
                 </div>
               </div>
            </div>
          </div>
        </div>
        <div class="modal-footer-premium p-4 border-top bg-light d-flex justify-content-end gap-3">
          <button @click="openEditModal(selectedRoom); showViewModal = false" class="btn btn-outline-modern px-4 py-2 fw-semibold rounded-pill">Edit Details</button>
          <button @click="showViewModal = false" class="btn btn-gold px-5 py-2 rounded-pill shadow-gold fw-bold text-uppercase">Close</button>
        </div>
      </div>
    </div>

    <!-- DEDICATED PROMO ROOMS MANAGEMENT MODAL -->
    <div v-if="showPromoModal" class="modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in">
      <div class="modal-card-premium bg-white rounded-5 shadow-2xl w-100 d-flex flex-column overflow-hidden my-auto" style="max-width: 620px; max-height: 90vh;">
        <div class="modal-header-premium p-4 d-flex justify-content-between align-items-center border-bottom bg-white-glass flex-shrink-0">
          <div class="d-flex align-items-center gap-3">
             <div class="brand-icon-box bg-gold text-white rounded-4 d-flex align-items-center justify-content-center shadow-gold">
               <i class="bi bi-tag-fill fs-4"></i>
             </div>
             <div>
               <h4 class="serif-font fw-bold mb-0 text-dark">{{ editingPromoRoom ? 'Edit Promo Deal' : 'Add Room to Promo' }}</h4>
               <p class="text-muted small mb-0">{{ editingPromoRoom ? `Configure promotional deal for Room #${editingPromoRoom.room_number}` : 'Choose a room and apply a special discount rate.' }}</p>
             </div>
          </div>
          <button class="btn-close-premium" @click="closePromoModal">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <form @submit.prevent="savePromo" class="d-flex flex-column flex-grow-1 overflow-hidden">
          <div class="p-4 d-flex flex-column gap-3 overflow-y-auto custom-scrollbar flex-grow-1">
            <!-- Room Selection (if adding new promo) -->
            <div v-if="!editingPromoRoom">
              <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-2">Select Room</label>
              <select class="form-select form-control-custom py-2" v-model="promoForm.room_id" required @change="onPromoRoomSelected">
                <option value="" disabled>-- Choose a room to promote --</option>
                <option v-for="r in rooms" :key="r.id" :value="r.id">
                  Room #{{ r.room_number }} ({{ r.room_type }}) &mdash; Regular: ₱{{ formatPrice(isPerHeadRoom(r) ? r.price_per_head : r.price_per_night) }}{{ isPerHeadRoom(r) ? '/head' : '/night' }}
                </option>
              </select>
            </div>
            <div v-else class="p-3 rounded-4 bg-light border d-flex justify-content-between align-items-center">
              <div>
                <span class="d-block fw-bold text-dark">Room #{{ editingPromoRoom.room_number }}</span>
                <small class="text-muted">{{ editingPromoRoom.room_type }} &bull; Max {{ editingPromoRoom.max_occupancy }} Guests</small>
              </div>
              <div class="text-end">
                <small class="text-muted d-block x-small text-uppercase">Standard Rate</small>
                <span class="fw-bold text-secondary-dark">₱{{ formatPrice(isPerHeadRoom(editingPromoRoom) ? editingPromoRoom.price_per_head : editingPromoRoom.price_per_night) }}{{ isPerHeadRoom(editingPromoRoom) ? ' / head' : ' / night' }}</span>
              </div>
            </div>

            <!-- Promo Campaign Title -->
            <div>
              <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-2">Promo Badge / Campaign Title</label>
              <input 
                type="text" 
                class="form-control form-control-custom py-2" 
                v-model="promoForm.promo_title" 
                placeholder="e.g. 20% OFF Summer Flash Deal, Weekend Saver" 
                required
              >
            </div>

            <!-- Discount Mode Switcher -->
            <div>
              <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-2">Discount Mode</label>
              <div class="row g-2 mb-3">
                <div class="col-6">
                  <button 
                    type="button" 
                    class="btn w-100 py-2 rounded-3 small fw-bold border shadow-none" 
                    :class="promoForm.discount_type === 'percent' ? 'btn-gold text-white' : 'btn-light text-muted'"
                    @click="setPromoDiscountType('percent')"
                  >
                    <i class="bi bi-percent me-1"></i> Percentage Discount
                  </button>
                </div>
                <div class="col-6">
                  <button 
                    type="button" 
                    class="btn w-100 py-2 rounded-3 small fw-bold border shadow-none" 
                    :class="promoForm.discount_type === 'fixed' ? 'btn-gold text-white' : 'btn-light text-muted'"
                    @click="setPromoDiscountType('fixed')"
                  >
                    <i class="bi bi-cash-coin me-1"></i> Fixed Promo Price
                  </button>
                </div>
              </div>

              <!-- Percent Input -->
              <div v-if="promoForm.discount_type === 'percent'">
                <label class="form-label small text-muted fw-semibold mb-1">Discount Percentage (%)</label>
                <div class="input-group">
                  <input 
                    type="number" 
                    class="form-control form-control-custom py-2" 
                    v-model.number="promoForm.promo_discount_percent" 
                    min="1" 
                    max="99" 
                    placeholder="e.g. 15"
                    required
                    @input="recalculatePromoPriceFromPercent"
                  >
                  <span class="input-group-text bg-light border-0 fw-bold">%</span>
                </div>
              </div>

              <!-- Fixed Price Input -->
              <div v-else>
                <label class="form-label small text-muted fw-semibold mb-1">
                  {{ isCurrentPromoRoomPerHead ? 'Promo Price per Head (PHP)' : 'Promo Price per Night (PHP)' }}
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-0 fw-bold">₱</span>
                  <input 
                    type="number" 
                    class="form-control form-control-custom py-2" 
                    v-model.number="currentPromoPriceField" 
                    min="0" 
                    step="0.01" 
                    placeholder="e.g. 1200"
                    required
                    @input="recalculatePromoPercentFromPrice"
                  >
                </div>
              </div>
            </div>

            <!-- Live Calculation Preview Card -->
            <div class="p-3 rounded-4 bg-gold-lightest border border-gold-light mt-1">
              <div class="d-flex justify-content-between mb-1">
                <span class="text-muted small">Regular Price:</span>
                <span class="text-muted small text-decoration-line-through">₱{{ formatPrice(currentRegularRate) }}</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-success small fw-semibold">Guest Savings:</span>
                <span class="text-success small fw-bold">-₱{{ formatPrice(currentSavingsAmount) }} ({{ promoForm.promo_discount_percent || 0 }}% OFF)</span>
              </div>
              <div class="d-flex justify-content-between border-top border-gold-light pt-2">
                <span class="fw-bold text-dark">Promotional Rate:</span>
                <span class="h5 mb-0 fw-bold text-gold serif-font">₱{{ formatPrice(currentCalculatedPromoPrice) }}{{ isCurrentPromoRoomPerHead ? ' / head' : ' / night' }}</span>
              </div>
            </div>

            <!-- Optional Promo Description -->
            <div>
              <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">Promo Description (Optional)</label>
              <textarea class="form-control form-control-custom py-2" rows="2" v-model="promoForm.promo_description" placeholder="Brief note about this promotional offer..."></textarea>
            </div>
          </div>

          <div class="modal-footer-premium p-4 border-top bg-light d-flex justify-content-between flex-shrink-0">
            <button v-if="editingPromoRoom" type="button" class="btn btn-outline-danger rounded-pill px-4" @click="removePromoFromRoom(editingPromoRoom)">
              <i class="bi bi-trash me-1"></i> End Promo
            </button>
            <div v-else></div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light px-4 rounded-pill fw-semibold" @click="closePromoModal">Cancel</button>
              <button type="submit" class="btn btn-gold px-4 rounded-pill fw-bold text-uppercase shadow-gold" :disabled="savingPromo">
                <span v-if="savingPromo" class="spinner-border spinner-border-sm me-2"></span>
                Save Promotion
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- PACKAGE ADD / EDIT MODAL -->
    <div v-if="showPackageModal" class="modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in">
      <div class="modal-card-premium bg-white rounded-5 shadow-2xl w-100 d-flex flex-column overflow-hidden my-auto" style="max-width: 850px; max-height: 90vh;">
        
        <!-- Modal Header -->
        <div class="modal-header-premium p-4 d-flex justify-content-between align-items-center border-bottom bg-white-glass flex-shrink-0">
          <div class="d-flex align-items-center gap-3">
            <div class="brand-icon-box bg-gold text-white rounded-4 d-flex align-items-center justify-content-center shadow-gold">
              <i class="bi bi-box2-heart-fill fs-4" v-if="!editingPackage"></i>
              <i class="bi bi-pencil-square fs-4" v-else></i>
            </div>
            <div>
              <h4 class="serif-font fw-bold mb-0 text-dark">{{ editingPackage ? 'Edit Room Package' : 'Add Room Package' }}</h4>
              <p class="text-muted small mb-0">{{ editingPackage ? 'Update package inclusions, room assignment, and rates.' : 'Create a room & tour package deal.' }}</p>
            </div>
          </div>
          <button class="btn-close-premium" @click="closePackageModal">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <!-- Modal Form Body -->
        <form @submit.prevent="savePackage" class="d-flex flex-column flex-grow-1 overflow-hidden m-0">
          <div class="modal-body-custom p-4 overflow-y-auto flex-grow-1 d-flex flex-column gap-3">
            
            <div class="row g-3">
              <!-- Package Title -->
              <div class="col-md-8">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Package Title <span class="text-danger">*</span>
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.name" 
                  placeholder="e.g. Siargao Tour Package (3D,2N)" 
                  required
                >
              </div>

              <!-- Total Package Price -->
              <div class="col-md-4">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Package Price (PHP) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-0 fw-bold">₱</span>
                  <input 
                    type="number" 
                    class="form-control form-control-custom py-2" 
                    v-model.number="packageForm.price" 
                    min="0" 
                    step="0.01" 
                    placeholder="e.g. 5400" 
                    required
                  >
                </div>
              </div>
            </div>

            <!-- Subtitle / Tagline -->
            <div>
              <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                Subtitle / Headline
              </label>
              <input 
                type="text" 
                class="form-control form-control-custom py-2" 
                v-model="packageForm.tagline" 
                placeholder="e.g. 2 Nights Accommodation + 1 Day Island Hopping"
              >
            </div>

            <!-- Accommodation Link & Specs -->
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Apply to Room
                </label>
                <select class="form-select form-control-custom py-2" v-model="packageForm.room_id" @change="onPackageRoomSelect">
                  <option value="">General (No specific room #)</option>
                  <option v-for="room in rooms" :key="room.id" :value="room.id">
                    Room #{{ room.room_number }} ({{ room.room_type }})
                  </option>
                </select>
                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                  <i class="bi bi-check2-circle text-success me-1"></i>Room picture, specifications &amp; amenities are linked automatically.
                </small>
              </div>
              <div class="col-md-4">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Accommodation Label
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.room_type_label" 
                  placeholder="e.g. Private Room, Couple Room"
                >
              </div>
              <div class="col-md-4">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Tour Type
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.tour_type" 
                  placeholder="e.g. Joiners Tour, Exclusive Tour"
                >
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Duration
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.duration" 
                  placeholder="e.g. 3 Days, 2 Nights"
                >
              </div>
              <div class="col-md-6">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Guests / Capacity (Pax)
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.pax" 
                  placeholder="e.g. Good for 2 pax"
                >
              </div>
            </div>

            <!-- Inclusions: 2 Sections (Island Hopping & Tour Inclusions) -->
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  <i class="bi bi-compass text-info me-1"></i> Island Hopping
                </label>
                <small class="text-muted d-block xx-small mb-1">One destination per line</small>
                <textarea 
                  class="form-control form-control-custom py-2" 
                  rows="4" 
                  v-model="packageForm.tour_destinations_text" 
                  placeholder="Naked Island&#10;Daku Island&#10;Guyam Island&#10;Secret Island (If low tide)"
                ></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  <i class="bi bi-card-checklist text-success me-1"></i> Tour Inclusions
                </label>
                <small class="text-muted d-block xx-small mb-1">One per line</small>
                <textarea 
                  class="form-control form-control-custom py-2" 
                  rows="4" 
                  v-model="packageForm.tour_inclusions_text" 
                  placeholder="Lunch (Siargao Iconic Boodle fight)&#10;All entrance fees&#10;All environmental fees&#10;Cottage fees&#10;Tour guide&#10;FREE pick up &amp; Drop off"
                ></textarea>
              </div>
            </div>

            <!-- Location & Contact -->
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Location
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.location" 
                  placeholder="e.g. Pob. 1 General Luna (Along the road)"
                >
              </div>
              <div class="col-md-6">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Booking Contact Phone
                </label>
                <input 
                  type="text" 
                  class="form-control form-control-custom py-2" 
                  v-model="packageForm.contact_number" 
                  placeholder="e.g. 0950 560 2175"
                >
              </div>
            </div>

            <!-- Cover Image Upload & Status -->
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <label class="form-label admin-label small fw-bold text-muted text-uppercase tracking-wider mb-1">
                  Cover Photo
                </label>
                <input 
                  type="file" 
                  class="form-control form-control-custom py-2" 
                  accept="image/*" 
                  @change="onPackageFileChange"
                  ref="packageFileInput"
                >
              </div>
              <div class="col-md-4 pt-md-3">
                <div class="form-check form-switch d-flex align-items-center gap-2">
                  <input 
                    class="form-check-input ms-0" 
                    type="checkbox" 
                    id="packageActiveSwitch" 
                    v-model="packageForm.is_active"
                    style="width: 2.5em; height: 1.3em;"
                  >
                  <label class="form-check-label fw-bold small text-secondary-dark" for="packageActiveSwitch">
                    {{ packageForm.is_active ? 'Active Package' : 'Paused / Inactive' }}
                  </label>
                </div>
              </div>
            </div>

            <!-- Preview image if selected -->
            <div v-if="packageImagePreview" class="position-relative rounded-4 overflow-hidden border shadow-sm" style="max-height: 160px;">
              <img :src="packageImagePreview" class="w-100 h-100 object-fit-cover" style="max-height: 160px;">
              <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 start-0 m-2">Photo Preview</span>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="modal-footer-premium p-4 border-top bg-light d-flex justify-content-between flex-shrink-0">
            <button 
              v-if="editingPackage" 
              type="button" 
              class="btn btn-outline-danger rounded-pill px-4" 
              @click="confirmDeletePackage(editingPackage.id)"
            >
              <i class="bi bi-trash me-1"></i> Delete Package
            </button>
            <div v-else></div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light px-4 rounded-pill fw-semibold" @click="closePackageModal">
                Cancel
              </button>
              <button type="submit" class="btn btn-gold px-4 rounded-pill fw-bold text-uppercase shadow-gold" :disabled="savingPackage">
                <span v-if="savingPackage" class="spinner-border spinner-border-sm me-2"></span>
                {{ editingPackage ? 'Update Package' : 'Save Package' }}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- PACKAGE VIEW FLYER MODAL -->
    <div v-if="showPackageViewModal && selectedPackage" class="modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in">
      <div class="modal-card-premium bg-white rounded-5 shadow-2xl w-100 d-flex flex-column overflow-hidden my-auto" style="max-width: 720px; max-height: 90vh;">
        
        <!-- Tropical Flyer Header -->
        <div class="position-relative" style="height: 200px; background: linear-gradient(135deg, #1b382b 0%, #2a5240 100%);">
          <img 
            :src="getPackageDisplayImage(selectedPackage)" 
            @error="onPkgImageError($event)" 
            class="w-100 h-100 object-fit-cover opacity-50"
          >
          <div class="position-absolute top-0 end-0 p-3">
            <button class="btn-close-premium bg-white bg-opacity-75" @click="showPackageViewModal = false">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
          <div class="position-absolute bottom-0 start-0 p-4 text-white">
            <span class="badge bg-gold text-white px-3 py-1 rounded-pill small fw-bold text-uppercase mb-2">
              🌴 Special Tour Package 🌴
            </span>
            <h3 class="serif-font fw-bold mb-1">{{ selectedPackage.name }}</h3>
            <p class="mb-0 text-white-50 small" v-if="selectedPackage.tagline">{{ selectedPackage.tagline }}</p>
          </div>
        </div>

        <div class="p-4 overflow-y-auto flex-grow-1 d-flex flex-column gap-4">
          <!-- Rate and Duration Banner -->
          <div class="d-flex flex-wrap justify-content-between align-items-center p-3 rounded-4 bg-gold-lightest border border-gold-light">
            <div>
              <small class="text-muted text-uppercase fw-bold xx-small tracking-wider d-block">Package Rate</small>
              <span class="h3 fw-bold text-gold serif-font mb-0">₱{{ formatPrice(selectedPackage.price) }}</span>
              <span class="badge bg-gold-subtle text-dark border border-gold-subtle rounded-pill ms-2 px-2.5 py-1 small fw-bold">
                {{ selectedPackage.pax || 'Good for 2 pax' }}
              </span>
            </div>
            <div class="text-end">
              <span class="badge bg-white text-secondary-dark border px-3 py-2 rounded-pill small fw-bold">
                <i class="bi bi-clock-history me-1 text-gold"></i>{{ selectedPackage.duration || '3D, 2N' }}
              </span>
              <small class="text-muted d-block mt-1 x-small">{{ selectedPackage.tour_type || 'Joiners Tour' }}</small>
            </div>
          </div>

          <!-- LANDSCAPE ROOM CARD (Above Islands & Tour Inclusions) -->
          <div v-if="selectedPackage.room || selectedPackage.room_type_label" class="p-3.5 rounded-4 bg-light border">
            <div class="row g-3 align-items-center">
              <!-- Left: Landscape Room Picture -->
              <div class="col-sm-5 col-md-4">
                <div class="rounded-3 overflow-hidden position-relative shadow-sm bg-white" style="height: 140px;">
                  <img 
                    :src="getRoomDisplayImage(selectedPackage.room)" 
                    :alt="selectedPackage.room?.room_type || 'Room'"
                    class="w-100 h-100 object-fit-cover"
                    @error="onImageError($event, selectedPackage.room?.room_type)"
                  >
                  <span class="position-absolute top-0 start-0 m-2 badge bg-dark bg-opacity-75 text-white x-small rounded-pill px-2.5 py-1">
                    <i class="bi bi-door-closed-fill me-1 text-gold"></i>
                    {{ selectedPackage.room ? `Room #${selectedPackage.room.room_number}` : 'Room' }}
                  </span>
                  <div class="position-absolute bottom-0 start-0 w-100 px-2 py-1 bg-dark bg-opacity-70 text-white x-small d-flex justify-content-between align-items-center" v-if="selectedPackage.room">
                    <span><i class="bi bi-people-fill me-1"></i>Max {{ selectedPackage.room.max_occupancy || 2 }}</span>
                    <span v-if="selectedPackage.room.bed_type">{{ selectedPackage.room.bed_type }}</span>
                  </div>
                </div>
              </div>

              <!-- Right: Room Details & Amenities Grid -->
              <div class="col-sm-7 col-md-8 d-flex flex-column justify-content-between h-100">
                <div>
                  <div class="d-flex justify-content-between align-items-start mb-1 flex-wrap gap-1">
                    <div>
                      <span class="text-gold xx-small fw-bold text-uppercase tracking-wider d-block">Room Accommodation</span>
                      <h6 class="fw-bold text-dark mb-0">
                        {{ selectedPackage.room ? `Room #${selectedPackage.room.room_number} — ${selectedPackage.room.room_type}` : (selectedPackage.room_type_label || 'Private Room') }}
                      </h6>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 x-small fw-bold" v-if="selectedPackage.room">
                      Linked Room
                    </span>
                  </div>

                  <!-- Room Specs Badges (Spacious pills with comfortable gaps & margins) -->
                  <div class="d-flex flex-wrap align-items-center gap-2 my-2.5">
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs">
                      <i class="bi bi-people-fill text-gold"></i>
                      <span>Max {{ selectedPackage.room?.max_occupancy || 2 }} Guests</span>
                    </span>
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" v-if="selectedPackage.room?.bed_type">
                      <i class="bi bi-lamp-fill text-gold"></i>
                      <span>{{ selectedPackage.room.bed_type }}</span>
                    </span>
                    <span class="badge bg-light text-secondary-dark border px-3 py-1.5 rounded-pill small fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" v-if="selectedPackage.room?.room_size">
                      <i class="bi bi-aspect-ratio text-gold"></i>
                      <span>{{ selectedPackage.room.room_size }} sq.m</span>
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

                  <div class="row g-3" v-if="selectedPackage.room?.amenities && selectedPackage.room.amenities.length > 0">
                    <div 
                      v-for="amenity in selectedPackage.room.amenities" 
                      :key="amenity.id"
                      class="col-6 col-sm-6"
                    >
                      <div class="d-flex align-items-center gap-2.5 py-1 px-1">
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
                      v-for="(inc, idx) in selectedPackage.room_inclusions" 
                      :key="idx"
                      class="col-6 col-sm-6"
                    >
                      <div class="d-flex align-items-center gap-2.5 py-1 px-1">
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

          <!-- 2 Columns Below: Islands & Tour Inclusions -->
          <div class="row g-3">
            <!-- Left: Island Hopping -->
            <div class="col-md-6">
              <div class="p-3 rounded-4 bg-light border h-100">
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                  <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-compass text-info"></i>
                    <span class="fw-bold small text-uppercase tracking-wider text-dark">Islands to Visit</span>
                  </span>
                  <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill x-small px-2 py-0.5">
                    Island Hopping
                  </span>
                </div>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small text-dark">
                  <li v-for="(dest, idx) in selectedPackage.tour_destinations" :key="idx" class="d-flex align-items-center gap-2">
                    <span class="fs-6">🏝️</span>
                    <span class="fw-medium">{{ dest }}</span>
                  </li>
                  <li v-if="!selectedPackage.tour_destinations || selectedPackage.tour_destinations.length === 0" class="text-muted fst-italic">
                    Island hopping tour
                  </li>
                </ul>
              </div>
            </div>

            <!-- Right: Tour Inclusions -->
            <div class="col-md-6">
              <div class="p-3 rounded-4 bg-light border h-100">
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                  <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-card-checklist text-success"></i>
                    <span class="fw-bold small text-uppercase tracking-wider text-dark">Tour Inclusions</span>
                  </span>
                  <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill x-small px-2 py-0.5">
                    All-Inclusive
                  </span>
                </div>
                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small text-dark">
                  <li v-for="(inc, idx) in selectedPackage.tour_inclusions" :key="idx" class="d-flex align-items-start gap-2">
                    <i class="bi bi-check-circle-fill text-gold mt-0.5 flex-shrink-0" style="font-size: 0.85rem;"></i>
                    <span class="lh-sm">{{ inc }}</span>
                  </li>
                  <li v-if="!selectedPackage.tour_inclusions || selectedPackage.tour_inclusions.length === 0" class="text-muted fst-italic">
                    All fees & guide included
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Location & Contact Footer Info -->
          <div class="p-3 rounded-4 bg-cream-subtle border d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <small class="text-muted d-block xx-small text-uppercase fw-bold">Location</small>
              <span class="fw-semibold text-dark small"><i class="bi bi-geo-alt-fill text-gold me-1"></i>{{ selectedPackage.location || 'Pob. 1 General Luna (Along the road)' }}</span>
            </div>
            <div>
              <small class="text-muted d-block xx-small text-uppercase fw-bold">For Bookings & Inquiries</small>
              <a :href="'tel:' + (selectedPackage.contact_number || '09505602175')" class="fw-bold text-gold text-decoration-none small">
                <i class="bi bi-telephone-fill me-1"></i>{{ selectedPackage.contact_number || '0950 560 2175' }}
              </a>
            </div>
          </div>
        </div>

        <div class="p-4 border-top bg-light d-flex justify-content-between">
          <button class="btn btn-light rounded-pill px-4" @click="showPackageViewModal = false">Close</button>
          <button class="btn btn-gold rounded-pill px-4 fw-bold" @click="showPackageViewModal = false; openEditPackageModal(selectedPackage)">
            <i class="bi bi-pencil-square me-1"></i> Edit Package
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive, watch } from 'vue';
import axios from 'axios';
import { notify, confirm } from '../../utils/sweetalert';
import AdminPagination from '../../components/AdminPagination.vue';

const rooms = ref([]);
const allAmenities = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const showModal = ref(false);
const editingRoom = ref(null);
const saving = ref(false);
const currentPage = ref(1);
const currentPromoPage = ref(1);
const pageSize = ref(5);
const activeTab = ref('basic');
const activeMainTab = ref('all'); // 'all' | 'promo' | 'packages'
const fileInput = ref(null);
const imagePreview = ref(null);
const showViewModal = ref(false);
const selectedRoom = ref(null);

// Packages State
const packages = ref([]);
const packagesLoading = ref(false);
const currentPackagePage = ref(1);
const showPackageModal = ref(false);
const editingPackage = ref(null);
const savingPackage = ref(false);
const showPackageViewModal = ref(false);
const selectedPackage = ref(null);
const packageFileInput = ref(null);
const packageImagePreview = ref(null);

const packageForm = reactive({
  name: '',
  tagline: '',
  duration: '3 Days, 2 Nights',
  pax: 'Good for 2 pax',
  price: 5400,
  room_id: '',
  room_type_label: 'Private Room',
  tour_type: 'Joiners Tour',
  room_inclusions_text: '',
  tour_destinations_text: '',
  tour_inclusions_text: '',
  description: '',
  location: 'Pob. 1 General Luna (Along the road)',
  contact_number: '0950 560 2175',
  is_active: true,
  image_file: null,
});

// Promo Modal State
const showPromoModal = ref(false);
const editingPromoRoom = ref(null);
const savingPromo = ref(false);

const promoForm = reactive({
  room_id: '',
  is_promo: true,
  discount_type: 'percent', // 'percent' | 'fixed'
  promo_discount_percent: 15,
  promo_price: 0,
  promo_price_per_head: 0,
  promo_title: 'Special Promotion',
  promo_description: ''
});

const formTabs = [
  { id: 'basic', name: 'Room Info', icon: 'bi-tag-fill' },
  { id: 'specs', name: 'Price & Size', icon: 'bi-gear-wide-connected' },
  { id: 'media', name: 'Photos & Amenities', icon: 'bi-stars' }
];

const form = reactive({
  room_number: '',
  room_type: 'Couple Room',
  price_per_night: 1500,
  price_per_head: 0,
  extra_bed_price: 300,
  max_occupancy: 2,
  max_extra_guests: 2,
  min_occupancy: 1,
  description: '',
  status: 'available',
  is_promo: false,
  promo_price: null,
  promo_price_per_head: null,
  promo_discount_percent: null,
  promo_title: '',
  promo_description: '',
  image: '',
  image_file: null,
  bed_type: 'Queen Size',
  room_size: '20',
  amenities: [],
  images: [], // File array for new uploads
  remove_images: [] // ID array for removals
});

const isPerHeadRoom = (room) => {
  return room && (room.room_type === 'Family Room' || room.room_type === 'Barkadahan Room');
};

const getRoomSavings = (room) => {
  if (!room) return 0;
  if (isPerHeadRoom(room)) {
    const orig = Number(room.price_per_head || 0);
    const promo = Number(room.promo_price_per_head || orig);
    return Math.max(0, orig - promo);
  } else {
    const orig = Number(room.price_per_night || 0);
    const promo = Number(room.promo_price || orig);
    return Math.max(0, orig - promo);
  }
};

const getRoomDiscountPercent = (room) => {
  if (!room) return 0;
  if (room.promo_discount_percent) return room.promo_discount_percent;
  const orig = isPerHeadRoom(room) ? Number(room.price_per_head || 0) : Number(room.price_per_night || 0);
  const savings = getRoomSavings(room);
  if (orig <= 0) return 0;
  return Math.round((savings / orig) * 100);
};

const promoRooms = computed(() => {
  return rooms.value.filter(r => r.is_promo);
});

const promoRoomsCount = computed(() => {
  return promoRooms.value.length;
});

const filteredPromoRooms = computed(() => {
  let list = promoRooms.value;
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(r => 
      r.room_number.toString().includes(q) || 
      r.room_type.toLowerCase().includes(q) ||
      (r.promo_title && r.promo_title.toLowerCase().includes(q))
    );
  }
  return sortRoomsByNumber(list);
});

const paginatedPromoRooms = computed(() => {
  const start = (currentPromoPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return filteredPromoRooms.value.slice(start, end);
});

const filteredPackages = computed(() => {
  let list = packages.value;
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(p => 
      p.name.toLowerCase().includes(q) || 
      (p.tagline && p.tagline.toLowerCase().includes(q)) ||
      (p.room_type_label && p.room_type_label.toLowerCase().includes(q)) ||
      (p.room && p.room.room_number.toString().includes(q))
    );
  }
  return list;
});

const paginatedPackages = computed(() => {
  const start = (currentPackagePage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return filteredPackages.value.slice(start, end);
});

const currentSelectedPromoRoom = computed(() => {
  if (editingPromoRoom.value) return editingPromoRoom.value;
  return rooms.value.find(r => r.id === Number(promoForm.room_id)) || null;
});

const isCurrentPromoRoomPerHead = computed(() => {
  return isPerHeadRoom(currentSelectedPromoRoom.value);
});

const currentRegularRate = computed(() => {
  const r = currentSelectedPromoRoom.value;
  if (!r) return 0;
  return isCurrentPromoRoomPerHead.value ? Number(r.price_per_head || 0) : Number(r.price_per_night || 0);
});

const currentPromoPriceField = computed({
  get() {
    return isCurrentPromoRoomPerHead.value ? promoForm.promo_price_per_head : promoForm.promo_price;
  },
  set(val) {
    if (isCurrentPromoRoomPerHead.value) {
      promoForm.promo_price_per_head = val;
    } else {
      promoForm.promo_price = val;
    }
  }
});

const currentCalculatedPromoPrice = computed(() => {
  if (isCurrentPromoRoomPerHead.value) {
    return Number(promoForm.promo_price_per_head || 0);
  }
  return Number(promoForm.promo_price || 0);
});

const currentSavingsAmount = computed(() => {
  return Math.max(0, currentRegularRate.value - currentCalculatedPromoPrice.value);
});

const recalculatePromoPriceFromPercent = () => {
  const regular = currentRegularRate.value;
  const pct = Number(promoForm.promo_discount_percent || 0);
  const discounted = Math.max(0, Math.round(regular * (1 - pct / 100)));
  if (isCurrentPromoRoomPerHead.value) {
    promoForm.promo_price_per_head = discounted;
  } else {
    promoForm.promo_price = discounted;
  }
};

const recalculatePromoPercentFromPrice = () => {
  const regular = currentRegularRate.value;
  const discounted = isCurrentPromoRoomPerHead.value ? Number(promoForm.promo_price_per_head || 0) : Number(promoForm.promo_price || 0);
  if (regular > 0) {
    const pct = Math.round(((regular - discounted) / regular) * 100);
    promoForm.promo_discount_percent = Math.max(0, Math.min(99, pct));
  }
};

const setPromoDiscountType = (type) => {
  promoForm.discount_type = type;
  if (type === 'percent') {
    recalculatePromoPriceFromPercent();
  }
};

const onPromoRoomSelected = () => {
  recalculatePromoPriceFromPercent();
};

const openAddPromoModal = () => {
  editingPromoRoom.value = null;
  const candidate = rooms.value.find(r => !r.is_promo) || rooms.value[0];
  Object.assign(promoForm, {
    room_id: candidate ? candidate.id : '',
    is_promo: true,
    discount_type: 'percent',
    promo_discount_percent: 15,
    promo_price: 0,
    promo_price_per_head: 0,
    promo_title: 'Special Promotion',
    promo_description: ''
  });
  if (candidate) {
    const regular = isPerHeadRoom(candidate) ? Number(candidate.price_per_head) : Number(candidate.price_per_night);
    const discounted = Math.round(regular * 0.85);
    if (isPerHeadRoom(candidate)) {
      promoForm.promo_price_per_head = discounted;
    } else {
      promoForm.promo_price = discounted;
    }
  }
  showPromoModal.value = true;
};

const openPromoModalForRoom = (room) => {
  editingPromoRoom.value = room;
  const regular = isPerHeadRoom(room) ? Number(room.price_per_head) : Number(room.price_per_night);
  const currentPromoPrice = isPerHeadRoom(room) 
    ? (room.promo_price_per_head || Math.round(regular * 0.85)) 
    : (room.promo_price || Math.round(regular * 0.85));

  const pct = room.promo_discount_percent || (regular > 0 ? Math.round(((regular - currentPromoPrice) / regular) * 100) : 15);

  Object.assign(promoForm, {
    room_id: room.id,
    is_promo: true,
    discount_type: 'percent',
    promo_discount_percent: pct > 0 ? pct : 15,
    promo_price: isPerHeadRoom(room) ? 0 : currentPromoPrice,
    promo_price_per_head: isPerHeadRoom(room) ? currentPromoPrice : 0,
    promo_title: room.promo_title || 'Special Promotion',
    promo_description: room.promo_description || ''
  });
  showPromoModal.value = true;
};

const closePromoModal = () => {
  showPromoModal.value = false;
  editingPromoRoom.value = null;
};

const savePromo = async () => {
  if (!promoForm.room_id) {
    notify.warning('Validation', 'Please select a room.');
    return;
  }
  savingPromo.value = true;
  try {
    const res = await axios.post(`/api/rooms/${promoForm.room_id}/promo`, {
      is_promo: true,
      promo_price: isCurrentPromoRoomPerHead.value ? null : Number(promoForm.promo_price),
      promo_price_per_head: isCurrentPromoRoomPerHead.value ? Number(promoForm.promo_price_per_head) : null,
      promo_discount_percent: Number(promoForm.promo_discount_percent) || null,
      promo_title: promoForm.promo_title || 'Special Promotion',
      promo_description: promoForm.promo_description || null,
    });

    const index = rooms.value.findIndex(r => r.id === Number(promoForm.room_id));
    if (index !== -1) {
      rooms.value[index] = res.data.room;
    }
    notify.success('Promo Active', res.data.message || 'Room promotion saved successfully.');
    closePromoModal();
  } catch (err) {
    notify.error('Promo Error', err.response?.data?.message || 'Failed to save promotion.');
  } finally {
    savingPromo.value = false;
  }
};

const removePromoFromRoom = async (room) => {
  const result = await confirm({
    title: 'Remove Promotion?',
    text: `Are you sure you want to remove promotion from Room #${room.room_number}? The room will return to regular pricing.`,
    confirmText: 'Yes, Remove Promo'
  });

  if (result.isConfirmed) {
    try {
      const res = await axios.delete(`/api/rooms/${room.id}/promo`);
      const index = rooms.value.findIndex(r => r.id === room.id);
      if (index !== -1) {
        rooms.value[index] = res.data.room;
      }
      if (showPromoModal.value) {
        closePromoModal();
      }
      notify.success('Promo Removed', res.data.message || 'Promotion removed successfully.');
    } catch (err) {
      notify.error('Error', err.response?.data?.message || 'Failed to remove promotion.');
    }
  }
};

const multipleImagePreviews = ref([]);
const existingImages = ref([]);

const sortRoomsByNumber = (list) => {
  if (!Array.isArray(list)) return [];
  return [...list].sort((a, b) => {
    return String(a.room_number || '').localeCompare(String(b.room_number || ''), undefined, {
      numeric: true,
      sensitivity: 'base'
    });
  });
};

const fetchRooms = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/rooms');
    rooms.value = sortRoomsByNumber(response.data);
  } catch (err) {
    console.error('Failed to fetch rooms', err);
    notify.error('Fetch Error', 'Failed to sync with room inventory.');
  } finally {
    loading.value = false;
  }
};

const fetchAllAmenities = async () => {
  try {
    const response = await axios.get('/api/amenities');
    allAmenities.value = response.data.filter(a => a.is_active);
  } catch (err) {
    console.error('Failed to fetch amenities', err);
  }
};

const availableRoomsCount = computed(() => {
  return rooms.value.filter(r => r.status === 'available').length;
});

const filteredRooms = computed(() => {
  let list = rooms.value;
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(r => 
      r.room_number.toString().includes(q) || 
      r.room_type.toLowerCase().includes(q)
    );
  }
  return sortRoomsByNumber(list);
});

const paginatedRooms = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value;
  const end = start + pageSize.value;
  return filteredRooms.value.slice(start, end);
});

const activeTabLabel = computed(() => {
  const tab = formTabs.find(t => t.id === activeTab.value);
  return tab ? tab.name : '';
});

watch(searchQuery, () => {
  currentPage.value = 1;
});

const openAddModal = () => {
  editingRoom.value = null;
  activeTab.value = 'basic';
  imagePreview.value = null;
  Object.assign(form, {
    room_number: '',
    room_type: 'Couple Room',
    price_per_night: 1500,
    price_per_head: 0,
    extra_bed_price: 300,
    max_occupancy: 2,
    max_extra_guests: 2,
    min_occupancy: 1,
    description: '',
    status: 'available',
    is_promo: false,
    promo_price: null,
    promo_price_per_head: null,
    promo_discount_percent: null,
    promo_title: '',
    promo_description: '',
    image: '',
    image_file: null,
    bed_type: 'Queen Size',
    room_size: '20',
    amenities: [],
    images: [],
    remove_images: []
  });
  multipleImagePreviews.value = [];
  existingImages.value = [];
  showModal.value = true;
};

const openEditModal = (room) => {
  editingRoom.value = room;
  activeTab.value = 'basic';
  imagePreview.value = null;
  const currentAmenityIds = room.amenities ? room.amenities.map(a => a.id) : [];
  
  Object.assign(form, {
    room_number: room.room_number,
    room_type: room.room_type,
    price_per_night: room.price_per_night,
    price_per_head: room.price_per_head || 0,
    extra_bed_price: room.extra_bed_price !== null && room.extra_bed_price !== undefined ? room.extra_bed_price : 300,
    max_occupancy: room.max_occupancy,
    max_extra_guests: room.max_extra_guests !== null && room.max_extra_guests !== undefined ? room.max_extra_guests : 2,
    min_occupancy: room.min_occupancy || 1,
    description: room.description || '',
    status: room.status,
    is_promo: !!room.is_promo,
    promo_price: room.promo_price,
    promo_price_per_head: room.promo_price_per_head,
    promo_discount_percent: room.promo_discount_percent,
    promo_title: room.promo_title || '',
    promo_description: room.promo_description || '',
    image: room.image || '',
    image_file: null,
    bed_type: room.bed_type || 'King Size',
    room_size: room.room_size || '25',
    amenities: currentAmenityIds,
    images: [],
    remove_images: []
  });
  existingImages.value = room.images || [];
  multipleImagePreviews.value = [];
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const viewRoom = (room) => {
  selectedRoom.value = room;
  showViewModal.value = true;
};

const handleFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    form.image_file = file;
    imagePreview.value = URL.createObjectURL(file);
  }
};

const handleMultipleFilesChange = (e) => {
  const files = Array.from(e.target.files);
  files.forEach(file => {
    form.images.push(file);
    multipleImagePreviews.value.push(URL.createObjectURL(file));
  });
};

const removeExistingImage = (id) => {
  existingImages.value = existingImages.value.filter(img => img.id !== id);
  form.remove_images.push(id);
};

const removeNewImage = (idx) => {
  form.images.splice(idx, 1);
  multipleImagePreviews.value.splice(idx, 1);
};

const removeMainImage = () => {
  form.image = '';
  form.image_file = null;
  imagePreview.value = null;
  if (fileInput.value) fileInput.value.value = '';
};

const saveRoom = async () => {
  if (!form.room_number) {
    activeTab.value = 'basic';
    notify.warning('Validation', 'Room number is required.');
    return;
  }
  saving.value = true;
  try {
    const formData = new FormData();
    Object.keys(form).forEach(key => {
      if (key === 'amenities') {
        formData.append(key, JSON.stringify(form[key] || []));
      } else if (key === 'remove_images') {
        formData.append(key, JSON.stringify(form[key] || []));
      } else if (key === 'is_promo') {
        formData.append(key, form[key] ? '1' : '0');
      } else if (key === 'images') {
        if (Array.isArray(form[key])) {
          form[key].forEach((file) => {
            formData.append('images[]', file);
          });
        }
      } else if (key === 'image_file') {
        if (form[key]) {
          formData.append(key, form[key]);
        }
      } else if (key === 'image') {
        if (form[key] !== null && form[key] !== undefined && form[key] !== 'null') {
          formData.append(key, form[key]);
        }
      } else if (form[key] !== null && form[key] !== undefined) {
        formData.append(key, form[key]);
      }
    });

    let res;
    if (editingRoom.value) {
      formData.append('_method', 'PUT');
      res = await axios.post(`/api/rooms/${editingRoom.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      const index = rooms.value.findIndex(r => r.id === editingRoom.value.id);
      if (index !== -1) {
        rooms.value[index] = res.data;
      }
      notify.success('Update Success', `Room #${form.room_number} data is now current.`);
    } else {
      res = await axios.post('/api/rooms', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      rooms.value.unshift(res.data);
      notify.success('Entry Recorded', `New Room #${form.room_number} added to the floor plan.`);
    }
    closeModal();
    fetchRooms();
  } catch (err) {
      const errorData = err.response?.data;
      let msg = errorData?.message;
      if (!msg && errorData?.errors) {
        msg = Object.values(errorData.errors).flat().join(' ');
      } else if (!msg && typeof errorData === 'object') {
        const values = Object.values(errorData).flat();
        if (values.length > 0 && typeof values[0] === 'string') {
          msg = values.join(' ');
        }
      }
      notify.error('Operation Failed', msg || 'Check form requirements.');
  } finally {
    saving.value = false;
  }
};

const confirmDelete = async (id) => {
  const res = await confirm({
    title: 'Delete Room?',
    text: 'Are you sure you want to delete this room?',
    confirmText: 'Yes, Delete'
  });

  if (res.isConfirmed) {
    try {
      await axios.delete(`/api/rooms/${id}`);
      rooms.value = rooms.value.filter(r => r.id !== id);
      notify.success('Deleted', 'Room has been removed.');
    } catch (err) {
      notify.error('Restriction', 'Room cannot be deleted while reservations are active.');
    }
  }
};

const formatIconClass = (icon) => {
  if (!icon) return '';
  return icon.startsWith('bi-') ? icon : `bi-${icon}`;
};

const formatPrice = (price) => {
  return parseFloat(price || 0).toLocaleString();
};

const statusBadgeClass = (status) => {
  switch (status) {
    case 'available': return 'bg-success-subtle text-success';
    case 'occupied': return 'bg-danger-subtle text-danger';
    case 'maintenance': return 'bg-warning-subtle text-warning';
    default: return 'bg-secondary-subtle text-secondary';
  }
};

const getDefaultImage = (type) => {
    const images = {
        'Family': '/images/unsplash/suite-room.jpg',
        'Barkadahan': '/images/unsplash/deluxe-room.jpg',
        'Couple': '/images/unsplash/standard-room.jpg'
    };
    const prefix = (type || '').split(' ')[0];
    return images[prefix] || images['Couple'];
};

const getRoomDisplayImage = (room) => {
    if (!room) return '/images/unsplash/standard-room.jpg';
    if (room.image && room.image !== 'null' && room.image !== '') return room.image;
    if (room.images && room.images.length > 0) {
        const first = room.images.find(img => img.image_path && img.image_path !== 'null');
        if (first) return first.image_path;
    }
    return getDefaultImage(room.room_type);
};

const onImageError = (e, type) => {
    e.target.src = getDefaultImage(type);
};

const isTabFilled = (tabId) => {
    if (tabId === 'basic') return !!form.room_number && !!form.room_type;
    if (tabId === 'specs') {
        const isPerHead = form.room_type === 'Family Room' || form.room_type === 'Barkadahan Room';
        const priceValid = isPerHead ? (form.price_per_head > 0) : (form.price_per_night > 0);
        return priceValid && form.max_occupancy > 0 && form.min_occupancy > 0;
    }
    if (tabId === 'media') return !!form.image || !!form.image_file || form.amenities.length > 0;
    return false;
};

const getNextTab = () => {
    if (activeTab.value === 'basic') return 'specs';
    if (activeTab.value === 'specs') return 'media';
    return 'media';
};

// Packages CRUD & Logic
const fetchPackages = async () => {
  packagesLoading.value = true;
  try {
    const res = await axios.get('/api/admin/room-packages');
    packages.value = res.data;
  } catch (err) {
    console.error('Failed to fetch packages from admin endpoint, trying fallback', err);
    try {
      const fallback = await axios.get('/api/room-packages');
      packages.value = fallback.data;
    } catch (e) {
      console.error('Fallback fetch also failed', e);
    }
  } finally {
    packagesLoading.value = false;
  }
};

const getPackageDisplayImage = (pkg) => {
  if (!pkg) return '/images/unsplash/hero-resort.jpg';
  if (pkg.image_url) return pkg.image_url;
  if (pkg.image) {
    if (pkg.image.startsWith('http') || pkg.image.startsWith('/')) return pkg.image;
    return `/storage/${pkg.image}`;
  }
  return '/images/unsplash/hero-resort.jpg';
};

const onPkgImageError = (e) => {
  e.target.src = '/images/unsplash/hero-resort.jpg';
};

const onPackageFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    packageForm.image_file = file;
    packageImagePreview.value = URL.createObjectURL(file);
  }
};

const onPackageRoomSelect = () => {
  if (packageForm.room_id) {
    const selected = rooms.value.find(r => r.id === Number(packageForm.room_id));
    if (selected) {
      packageForm.room_type_label = `${selected.room_type} (Room #${selected.room_number})`;
    }
  }
};

const openAddPackageModal = () => {
  editingPackage.value = null;
  packageImagePreview.value = null;
  if (packageFileInput.value) packageFileInput.value.value = '';

  Object.assign(packageForm, {
    name: 'Siargao Tour Package (3D,2N)',
    tagline: '2 Nights Accommodation + 1 Day Island Hopping',
    duration: '3 Days, 2 Nights',
    pax: 'Good for 2 pax',
    price: 5400,
    room_id: '',
    room_type_label: 'Private Room',
    tour_type: 'Joiners Tour',
    room_inclusions_text: '',
    tour_destinations_text: "Naked Island\nDaku Island\nGuyam Island\nSecret Island (If low tide)",
    tour_inclusions_text: "Lunch (Siargao Iconic Boodle fight)\nAll entrance fees\nAll environmental fees\nCottage fees\nTour guide\nFREE pick up & Drop off",
    description: '',
    location: 'Pob. 1 General Luna (Along the road)',
    contact_number: '0950 560 2175',
    is_active: true,
    image_file: null,
  });

  showPackageModal.value = true;
};

const openAddPackageModalForRoom = (room) => {
  openAddPackageModal();
  packageForm.room_id = room.id;
  packageForm.room_type_label = `${room.room_type} (Room #${room.room_number})`;
  activeMainTab.value = 'packages';
};

const openEditPackageModal = (pkg) => {
  editingPackage.value = pkg;
  packageImagePreview.value = getPackageDisplayImage(pkg);
  if (packageFileInput.value) packageFileInput.value.value = '';

  Object.assign(packageForm, {
    name: pkg.name || '',
    tagline: pkg.tagline || '',
    duration: pkg.duration || '3 Days, 2 Nights',
    pax: pkg.pax || 'Good for 2 pax',
    price: Number(pkg.price || 0),
    room_id: pkg.room_id || '',
    room_type_label: pkg.room_type_label || 'Private Room',
    tour_type: pkg.tour_type || 'Joiners Tour',
    room_inclusions_text: '',
    tour_destinations_text: Array.isArray(pkg.tour_destinations) ? pkg.tour_destinations.join('\n') : (pkg.tour_destinations || ''),
    tour_inclusions_text: Array.isArray(pkg.tour_inclusions) ? pkg.tour_inclusions.join('\n') : (pkg.tour_inclusions || ''),
    description: pkg.description || '',
    location: pkg.location || 'Pob. 1 General Luna (Along the road)',
    contact_number: pkg.contact_number || '0950 560 2175',
    is_active: !!pkg.is_active,
    image_file: null,
  });

  showPackageModal.value = true;
};

const closePackageModal = () => {
  showPackageModal.value = false;
  editingPackage.value = null;
  packageImagePreview.value = null;
};

const viewPackageDetails = (pkg) => {
  selectedPackage.value = pkg;
  showPackageViewModal.value = true;
};

const togglePackageStatus = async (pkg) => {
  try {
    const res = await axios.patch(`/api/admin/room-packages/${pkg.id}/toggle`);
    pkg.is_active = res.data.is_active;
    notify.success('Status Updated', `Package "${pkg.name}" is now ${pkg.is_active ? 'Active' : 'Paused'}.`);
  } catch (err) {
    console.error('Toggle status failed', err);
    notify.error('Update Failed', 'Could not update package status.');
  }
};

const savePackage = async () => {
  savingPackage.value = true;
  try {
    const formData = new FormData();
    formData.append('name', packageForm.name);
    formData.append('price', packageForm.price);
    if (packageForm.tagline) formData.append('tagline', packageForm.tagline);
    if (packageForm.duration) formData.append('duration', packageForm.duration);
    if (packageForm.pax) formData.append('pax', packageForm.pax);
    if (packageForm.room_id) formData.append('room_id', packageForm.room_id);
    if (packageForm.room_type_label) formData.append('room_type_label', packageForm.room_type_label);
    if (packageForm.tour_type) formData.append('tour_type', packageForm.tour_type);
    if (packageForm.description) formData.append('description', packageForm.description);
    if (packageForm.location) formData.append('location', packageForm.location);
    if (packageForm.contact_number) formData.append('contact_number', packageForm.contact_number);
    formData.append('is_active', packageForm.is_active ? '1' : '0');

    formData.append('room_inclusions', '');
    formData.append('tour_destinations', packageForm.tour_destinations_text || '');
    formData.append('tour_inclusions', packageForm.tour_inclusions_text || '');

    if (packageForm.image_file) {
      formData.append('image', packageForm.image_file);
    }

    if (editingPackage.value) {
      await axios.post(`/api/admin/room-packages/${editingPackage.value.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      notify.success('Package Updated', 'Room package updated successfully.');
    } else {
      await axios.post('/api/admin/room-packages', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      notify.success('Package Created', 'Room package added successfully.');
    }

    closePackageModal();
    fetchPackages();
  } catch (err) {
    console.error('Failed to save package', err);
    notify.error('Save Failed', err.response?.data?.message || 'Check form requirements.');
  } finally {
    savingPackage.value = false;
  }
};

const confirmDeletePackage = async (id) => {
  const result = await confirm(
    'Delete Room Package?',
    'Are you sure you want to delete this package? This action cannot be undone.',
    'Yes, delete it!'
  );

  if (result.isConfirmed) {
    try {
      await axios.delete(`/api/admin/room-packages/${id}`);
      notify.success('Deleted!', 'Room package has been removed.');
      if (showPackageModal.value) closePackageModal();
      fetchPackages();
    } catch (err) {
      console.error('Delete failed', err);
      notify.error('Delete Failed', err.response?.data?.message || 'Could not delete package.');
    }
  }
};

onMounted(() => {
    fetchRooms();
    fetchAllAmenities();
    fetchPackages();
});
</script>

<style scoped>
.stats-card {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.stats-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 24px rgba(0,0,0,0.06);
}

.icon-box {
  width: 54px;
  height: 54px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-icon {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-light-gold {
  background: var(--primary-gold-subtle);
  color: var(--primary-gold);
}

.btn-light-gold:hover {
  background: var(--primary-gold);
  color: white;
  transform: rotate(15deg);
}

.btn-light-danger {
  background: rgba(220, 53, 69, 0.08);
  color: #dc3545;
}

.btn-light-danger:hover {
  background: #dc3545;
  color: white;
  transform: scale(1.1);
}

.btn-light-info {
  background: rgba(13, 202, 240, 0.12);
  color: #087990;
}

.btn-light-info:hover {
  background: #0dcaf0;
  color: white;
  transform: scale(1.08);
}

/* PREMIUM MODAL STYLES */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(10px);
  z-index: 10000;
  overflow-y: auto;
}

.modal-card-premium {
  background: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.shadow-2xl {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.brand-icon-box {
  width: 48px;
  height: 48px;
}

.shadow-gold {
  box-shadow: 0 8px 16px rgba(188, 145, 81, 0.3);
}

.btn-close-premium {
  width: 40px; height: 40px;
  border-radius: 50%;
  border: none;
  background: #f1f5f9;
  color: #64748b;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s;
}

.btn-close-premium:hover {
  background: #e2e8f0;
  color: #0f172a;
  transform: rotate(90deg);
}

/* Sidebar Tabs */
.nav-tab-btn {
  border: none;
  background: transparent;
  text-align: left;
  color: #64748b;
}

.nav-tab-btn:hover {
  background: rgba(212, 180, 131, 0.1);
  color: var(--primary-gold);
}

.active-tab {
  background: white !important;
  color: var(--primary-gold) !important;
}

.active-tab i {
  color: var(--primary-gold);
}

/* Modern Floating Inputs */
.floating-group {
  position: relative;
}

.input-modern-xl {
  width: 100%;
  padding: 1.25rem 1rem 0.75rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 16px;
  font-size: 1rem;
  font-weight: 500;
  transition: all 0.3s;
}

.input-modern-xl:focus {
  background: white;
  border-color: var(--primary-gold);
  box-shadow: 0 0 0 4px rgba(188, 145, 81, 0.08);
  outline: none;
}

.floating-group label {
  position: absolute;
  top: 1rem;
  left: 1rem;
  font-size: 0.9rem;
  color: #94a3b8;
  pointer-events: none;
  transition: all 0.2s;
}

.input-modern-xl:focus ~ label,
.input-modern-xl:not(:placeholder-shown) ~ label {
  top: 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--primary-gold);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Price Prefix */
.price-field .input-modern-xl {
  padding-left: 2.5rem;
}

.currency-prefix {
  position: absolute;
  left: 1.25rem;
  top: 1.1rem;
  font-weight: 700;
  color: #94a3b8;
  transition: color 0.3s;
}

.input-modern-xl:focus ~ .currency-prefix {
  color: var(--primary-gold);
}

/* Status Selectors */
.btn-status-modern {
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  font-weight: 600;
  border-radius: 14px;
}

.btn-check:checked + .btn-status-modern {
  background: rgba(25, 135, 84, 0.1);
  border-color: #198754;
  color: #198754;
}

.btn-check:checked + .btn-status-modern.maint {
  background: rgba(255, 193, 7, 0.1);
  border-color: #ffc107;
  color: #ffc107;
}

/* Amenity Checkboxes */
.amenity-checkbox-modern label {
  cursor: pointer;
  background: white;
}

.amenity-checkbox-modern.selected label {
  background: rgba(188, 145, 81, 0.04);
  border-color: var(--primary-gold);
}

.amenity-icon-mini {
  width: 38px; height: 38px;
  background: #f8fafc;
  color: #94a3b8;
  transition: all 0.2s;
}

.amenity-checkbox-modern.selected .amenity-icon-mini {
  background: var(--primary-gold);
  color: white;
}

.selected-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  background: #e2e8f0;
  transition: all 0.2s;
}

.amenity-checkbox-modern.selected .selected-dot {
  background: var(--primary-gold);
  transform: scale(1.3);
}

/* Step Dots */
.step-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #e2e8f0;
  transition: all 0.3s;
}

.active-dot {
  width: 24px;
  border-radius: 4px;
  background: var(--primary-gold);
}

.done-dot {
  background: #64748b;
}

/* General Animations */
.animate-tab-content {
  animation: slideLeft 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
}

@keyframes slideLeft {
  from { opacity: 0; transform: translateX(15px); }
  to { opacity: 1; transform: translateX(0); }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1;
}

.group:hover .preview-overlay {
   opacity: 1 !important;
}

.preview-overlay {
    transition: opacity 0.3s ease;
}

.z-2 { z-index: 2; }
.feature-badge {
  background: #ffffff;
  transition: all 0.2s ease;
  min-width: fit-content;
}
.feature-badge:hover {
  border-color: var(--primary-gold) !important;
  transform: translateY(-2px);
}
.feature-icon {
  flex-shrink: 0;
}
.text-gold-dark {
  color: #9A7640;
}
.ls-tight {
  letter-spacing: -0.2px;
}
.letter-spacing-wide {
  letter-spacing: 1px;
}
/* Gallery Image Management */
.gallery-upload-grid {
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
}

.aspect-square {
    aspect-ratio: 1 / 1;
}

.gallery-item-premium {
    transition: all 0.3s ease;
}

.gallery-item-premium:hover {
    transform: scale(1.05);
    z-index: 2;
}

.btn-remove-img {
    width: 24px;
    height: 24px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-remove-img:hover {
    background: #dc2626;
    transform: scale(1.2);
}

.gallery-add-card {
    background: #f8fafc;
    transition: all 0.3s;
}

.gallery-add-card:hover {
    background: var(--primary-gold-subtle);
    border-color: var(--primary-gold) !important;
}

.new-badge {
    border-bottom-right-radius: 8px;
    font-weight: 800;
    letter-spacing: 0.5px;
}
.z-3 { z-index: 3; }
</style>
