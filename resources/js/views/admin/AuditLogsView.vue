<template>
  <div class="audit-logs-view p-1">
    <!-- Summary KPI Cards -->
    <div class="row g-4 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="stats-card-premium bg-white animate-fade-up">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-bold text-uppercase tracking-wider mb-2 d-block">Total Bookings</span>
              <h3 class="mb-0 fw-bold text-secondary-dark serif-font">{{ summary.total_bookings.toLocaleString() }}</h3>
              <small class="text-muted" style="font-size: 0.72rem;">{{ summary.total_activities.toLocaleString() }} total activities</small>
            </div>
            <div class="stats-icon-premium bg-gold-subtle text-gold">
              <i class="bi bi-journal-check"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="stats-card-premium bg-white animate-fade-up" style="animation-delay: 0.05s;">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-bold text-uppercase tracking-wider mb-2 d-block">Total Guests Booked</span>
              <h3 class="mb-0 fw-bold text-secondary-dark serif-font">{{ summary.total_guests_booked.toLocaleString() }}</h3>
              <small class="text-muted" style="font-size: 0.72rem;">
                {{ summary.total_adults_booked }} adults<template v-if="summary.total_children_booked > 0">, {{ summary.total_children_booked }} children</template>
              </small>
            </div>
            <div class="stats-icon-premium bg-primary-subtle text-primary">
              <i class="bi bi-people-fill"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="stats-card-premium bg-white animate-fade-up" style="animation-delay: 0.1s;">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-bold text-uppercase tracking-wider mb-2 d-block">Booked by Guests</span>
              <h3 class="mb-0 fw-bold text-secondary-dark serif-font">{{ summary.booked_by_guest.toLocaleString() }}</h3>
              <small class="text-muted" style="font-size: 0.72rem;">Self-service bookings</small>
            </div>
            <div class="stats-icon-premium bg-success-subtle text-success">
              <i class="bi bi-person-check-fill"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="stats-card-premium bg-white animate-fade-up" style="animation-delay: 0.15s;">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="text-muted small fw-bold text-uppercase tracking-wider mb-2 d-block">Booked by Staff / Admin</span>
              <h3 class="mb-0 fw-bold text-secondary-dark serif-font">{{ summary.booked_by_staff_total.toLocaleString() }}</h3>
              <small class="text-muted" style="font-size: 0.72rem;">
                {{ summary.booked_by_staff }} staff · {{ summary.booked_by_admin }} admin
              </small>
            </div>
            <div class="stats-icon-premium bg-warning-subtle text-warning-emphasis">
              <i class="bi bi-person-badge-fill"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden animate-fade-up">
      <div class="card-header bg-white py-4 px-4 border-0">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div>
              <h5 class="serif-font fw-bold mb-0 text-secondary-dark">Booking Activity Audit Log</h5>
              <p class="text-muted small mb-0">Track room bookings, guest counts, dates, times, and who booked each room</p>
            </div>
            <!-- View Toggle -->
            <div class="btn-group btn-group-sm shadow-sm ms-lg-2" role="group">
              <button
                class="btn btn-sm fw-bold"
                :class="activeTab === 'logs' ? 'btn-gold text-white' : 'btn-outline-gold'"
                @click="activeTab = 'logs'"
                style="font-size: 0.75rem;"
              >
                <i class="bi bi-list-ul me-1"></i> Activity Log
              </button>
              <button
                class="btn btn-sm fw-bold"
                :class="activeTab === 'rooms' ? 'btn-gold text-white' : 'btn-outline-gold'"
                @click="activeTab = 'rooms'"
                style="font-size: 0.75rem;"
              >
                <i class="bi bi-door-closed me-1"></i> By Room ({{ roomSummary.length }})
              </button>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <button
              @click="exportCsv"
              class="btn btn-outline-gold btn-sm rounded-pill px-3 fw-bold"
              :disabled="exporting || loading"
              title="Export filtered audit logs as CSV"
            >
              <span v-if="exporting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-file-earmark-spreadsheet me-1"></i>
              Export CSV
            </button>
            <button
              @click="printReport"
              class="btn btn-gold btn-sm rounded-pill px-3 fw-bold shadow-sm"
              :disabled="printing || loading"
              title="Open print layout & print report"
            >
              <span v-if="printing" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-printer me-1"></i>
              Print Report
            </button>
          </div>
        </div>

        <!-- Filters Row -->
        <div class="row g-2 align-items-center pt-2 border-top">
          <div class="col-12 col-md-4 col-xl-3">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
              <input
                type="text"
                class="form-control bg-light border-0 shadow-none"
                placeholder="Search person, guest, room, ID..."
                v-model="filters.search"
                @input="debouncedFetch"
              >
            </div>
          </div>

          <div class="col-6 col-md-4 col-xl-2">
            <select
              class="form-select form-select-sm bg-light border-0 shadow-none fw-medium"
              v-model="filters.actor_role"
              @change="applyFilters"
            >
              <option value="all">All Roles</option>
              <option value="guest">Guest</option>
              <option value="staff">Staff</option>
              <option value="admin">Admin</option>
              <option value="staff_and_admin">Staff &amp; Admin</option>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2" v-if="activeTab === 'logs'">
            <select
              class="form-select form-select-sm bg-light border-0 shadow-none fw-medium"
              v-model="filters.action"
              @change="applyFilters"
            >
              <option value="all">All Activities</option>
              <option value="booking_created">Booking Created</option>
              <option value="booking_confirmed">Booking Confirmed</option>
              <option value="check_in">Check-in</option>
              <option value="check_out">Check-out</option>
              <option value="booking_rescheduled">Rescheduled</option>
              <option value="booking_updated">Updated</option>
              <option value="payment_updated">Payment Updated</option>
              <option value="booking_cancelled">Cancelled</option>
              <option value="booking_deleted">Deleted</option>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2" v-if="activeTab === 'logs'">
            <select
              class="form-select form-select-sm bg-light border-0 shadow-none fw-medium"
              v-model="filters.room_id"
              @change="applyFilters"
            >
              <option value="all">All Rooms</option>
              <option v-for="room in rooms" :key="room.id" :value="room.id">
                {{ room.room_type }} #{{ room.room_number }}
              </option>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-2" v-if="activeTab === 'logs'">
            <select
              class="form-select form-select-sm bg-light border-0 shadow-none fw-medium"
              v-model="filters.sort"
              @change="applyFilters"
            >
              <option value="latest">Latest Updated First</option>
              <option value="oldest">Oldest First</option>
            </select>
          </div>

          <div class="col-6 col-md-4 col-xl-auto d-flex align-items-center gap-1">
            <input
              type="date"
              class="form-control form-control-sm bg-light border-0 shadow-none"
              v-model="filters.start_date"
              @change="applyFilters"
              title="From Date"
              style="width: 130px;"
            >
            <span class="text-muted small">–</span>
            <input
              type="date"
              class="form-control form-control-sm bg-light border-0 shadow-none"
              v-model="filters.end_date"
              @change="applyFilters"
              title="To Date"
              style="width: 130px;"
            >
          </div>

          <div class="col-auto" v-if="hasActiveFilters">
            <button
              @click="clearFilters"
              class="btn btn-sm btn-outline-danger rounded-3 px-2.5 fw-semibold"
              style="font-size: 0.78rem;"
            >
              <i class="bi bi-x-lg me-1"></i>Clear
            </button>
          </div>
        </div>
      </div>

      <div class="card-body p-0">
        <!-- Loading State -->
        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-gold" role="status"></div>
          <p class="text-muted mt-2 small mb-0">Loading audit logs...</p>
        </div>

        <!-- Tab 1: Activity Log Table -->
        <div v-else-if="activeTab === 'logs'">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-4 border-0 text-muted small fw-bold text-uppercase text-nowrap">Date &amp; Time</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-nowrap">Activity</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-nowrap">Booked / Performed By</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-nowrap">Room &amp; Guests</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-nowrap">Guest &amp; Stay Schedule</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-nowrap">Amount</th>
                  <th class="pe-4 border-0 text-muted small fw-bold text-uppercase text-end text-nowrap">Details</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="log in logs" :key="log.id">
                  <!-- Date & Time -->
                  <td class="ps-4 py-3 text-nowrap">
                    <div class="small fw-bold text-secondary-dark">{{ formatDate(log.updated_at || log.created_at) }}</div>
                    <small class="text-muted" style="font-size: 0.74rem;">
                      <i class="bi bi-clock me-1"></i>{{ formatTime(log.updated_at || log.created_at) }}
                    </small>
                  </td>

                  <!-- Activity -->
                  <td class="py-3 text-nowrap">
                    <span class="badge rounded-pill px-2.5 py-1.5 fw-bold text-uppercase" :class="actionBadgeClass(log.action)" style="font-size: 0.65rem;">
                      {{ formatActionLabel(log.action) }}
                    </span>
                    <div v-if="log.entity_id" class="text-muted mt-1" style="font-size: 0.7rem;">
                      #RES-{{ String(log.entity_id).padStart(3, '0') }}
                    </div>
                  </td>

                  <!-- Booked / Performed By -->
                  <td class="py-3">
                    <div class="d-flex align-items-center gap-2">
                      <div
                        class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                        :class="roleAvatarClass(log.actor_role)"
                        style="width: 34px; height: 34px; font-size: 0.8rem;"
                      >
                        {{ (log.actor_name || 'S').charAt(0).toUpperCase() }}
                      </div>
                      <div class="overflow-hidden" style="max-width: 190px;">
                        <div class="d-flex align-items-center gap-1.5">
                          <span class="small fw-bold text-secondary-dark text-truncate">{{ log.actor_name || 'System' }}</span>
                          <span class="badge rounded-pill text-uppercase fw-bold" :class="roleBadgeClass(log.actor_role)" style="font-size: 0.6rem;">
                            {{ log.actor_role || 'guest' }}
                          </span>
                        </div>
                        <small class="text-muted d-block text-truncate" style="font-size: 0.72rem;" v-if="log.actor_email">
                          {{ log.actor_email }}
                        </small>
                      </div>
                    </div>
                  </td>

                  <!-- Room & Guests -->
                  <td class="py-3 text-nowrap">
                    <div class="small fw-bold text-secondary-dark">
                      {{ log.room_type || 'Room' }}
                      <span v-if="log.room_number" class="text-gold">#{{ log.room_number }}</span>
                    </div>
                    <small class="text-muted d-block" style="font-size: 0.74rem;">
                      <i class="bi bi-people me-1"></i>
                      <strong>{{ log.guests_count }}</strong> {{ log.guests_count === 1 ? 'Guest' : 'Guests' }}
                      <span v-if="log.adults_count || log.children_count" class="ms-1">
                        ({{ log.adults_count }}A<template v-if="log.children_count > 0">, {{ log.children_count }}C</template>)
                      </span>
                    </small>
                  </td>

                  <!-- Guest & Stay Schedule -->
                  <td class="py-3">
                    <div class="small fw-semibold text-secondary-dark text-truncate" style="max-width: 180px;">
                      {{ log.guest_name || '—' }}
                    </div>
                    <small class="text-muted d-block text-nowrap" style="font-size: 0.72rem;" v-if="log.check_in && log.check_out">
                      <i class="bi bi-calendar-event me-1"></i>{{ formatShortDate(log.check_in) }} – {{ formatShortDate(log.check_out) }}
                    </small>
                  </td>

                  <!-- Amount -->
                  <td class="py-3 text-nowrap">
                    <div class="small fw-bold text-secondary-dark" v-if="log.total_amount !== null && log.total_amount !== undefined">
                      ₱{{ formatAmount(log.total_amount) }}
                    </div>
                    <span v-if="log.payment_status" class="badge rounded-pill text-uppercase" :class="paymentBadgeClass(log.payment_status)" style="font-size: 0.6rem;">
                      {{ formatActionLabel(log.payment_status) }}
                    </span>
                  </td>

                  <!-- Details Action -->
                  <td class="pe-4 py-3 text-end text-nowrap">
                    <button
                      class="btn btn-action-view"
                      title="View Full Activity Details"
                      @click="openDetailModal(log)"
                    >
                      <i class="bi bi-eye"></i>
                    </button>
                  </td>
                </tr>

                <tr v-if="logs.length === 0">
                  <td colspan="7" class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-2 d-block mb-2 opacity-25"></i>
                    No audit log entries match your filters.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <AdminPagination
            :current-page="pagination.current_page"
            :total-items="pagination.total"
            :page-size="pagination.per_page"
            @change="handlePageChange"
            @page-size-change="handlePageSizeChange"
          />
        </div>

        <!-- Tab 2: Room Booking Summary Table -->
        <div v-else>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-4 border-0 text-muted small fw-bold text-uppercase">Room</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-center">Bookings Logged</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-center">Total Guests Booked</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-center">Booked by Guest</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase text-center">Booked by Staff / Admin</th>
                  <th class="border-0 text-muted small fw-bold text-uppercase">Last Booking Date &amp; Time</th>
                  <th class="pe-4 border-0 text-muted small fw-bold text-uppercase text-end">Logs</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="room in roomSummary" :key="`${room.room_id}-${room.room_number}`">
                  <td class="ps-4 py-3">
                    <span class="fw-bold text-secondary-dark">{{ room.room_type }}</span>
                    <span class="text-gold fw-bold ms-1">#{{ room.room_number }}</span>
                  </td>
                  <td class="text-center fw-bold text-secondary-dark">{{ room.bookings_count }}</td>
                  <td class="text-center">
                    <span class="badge rounded-pill px-3 py-1.5 fw-bold" :class="Number(room.total_guests) > 0 ? 'bg-gold-subtle text-gold' : 'bg-light text-muted'">
                      <i class="bi bi-people-fill me-1"></i>{{ room.total_guests }} {{ Number(room.total_guests) === 1 ? 'Guest' : 'Guests' }}
                    </span>
                    <div v-if="Number(room.total_guests) > 0" class="text-muted mt-0.5" style="font-size: 0.7rem;">
                      {{ room.total_adults }}A<template v-if="Number(room.total_children) > 0">, {{ room.total_children }}C</template>
                    </div>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-bold">{{ room.guest_bookings }}</span>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-bold">{{ room.staff_bookings }}</span>
                  </td>
                  <td class="text-nowrap">
                    <template v-if="room.last_booked_at">
                      <div class="small fw-semibold text-secondary-dark">{{ formatDate(room.last_booked_at) }}</div>
                      <small class="text-muted" style="font-size: 0.72rem;">{{ formatTime(room.last_booked_at) }}</small>
                    </template>
                    <span v-else class="small text-muted">No bookings yet</span>
                  </td>
                  <td class="pe-4 text-end">
                    <button
                      v-if="room.room_id"
                      class="btn btn-sm btn-outline-gold rounded-pill px-3 fw-bold"
                      style="font-size: 0.72rem;"
                      @click="filterByRoom(room.room_id)"
                    >
                      View Logs
                    </button>
                  </td>
                </tr>

                <tr v-if="roomSummary.length === 0">
                  <td colspan="7" class="text-center py-5 text-muted">
                    No room booking records found for the selected filters.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity Detail Modal -->
    <teleport to="body">
      <div
        v-if="selectedLog"
        class="modal-overlay d-flex align-items-center justify-content-center p-3 animate-fade-in"
        @click.self="selectedLog = null"
      >
        <div class="bg-white rounded-4 shadow-lg overflow-hidden animate-scale-up" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;">
          <div class="px-4 py-3 border-bottom d-flex align-items-center justify-content-between bg-light-subtle">
            <div class="d-flex align-items-center gap-2">
              <span class="badge rounded-pill px-2.5 py-1 fw-bold text-uppercase" :class="actionBadgeClass(selectedLog.action)" style="font-size: 0.68rem;">
                {{ formatActionLabel(selectedLog.action) }}
              </span>
              <h6 class="mb-0 fw-bold text-secondary-dark serif-font">Activity #{{ selectedLog.id }}</h6>
            </div>
            <button class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 30px; height: 30px; padding: 0;" @click="selectedLog = null">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>

          <div class="p-4">
            <p class="small text-secondary-dark bg-light rounded-3 p-3 mb-4">
              {{ selectedLog.description || 'No description recorded.' }}
            </p>

            <div class="row g-3 small">
              <div class="col-sm-6">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Date &amp; Time</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">
                  {{ formatDate(selectedLog.created_at) }} at {{ formatTime(selectedLog.created_at) }}
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Booked / Performed By</div>
                <div class="fw-semibold text-secondary-dark mt-0.5 d-flex align-items-center gap-1.5">
                  <span>{{ selectedLog.actor_name || 'System' }}</span>
                  <span class="badge rounded-pill text-uppercase" :class="roleBadgeClass(selectedLog.actor_role)" style="font-size: 0.6rem;">
                    {{ selectedLog.actor_role || 'guest' }}
                  </span>
                </div>
                <div class="text-muted" style="font-size: 0.74rem;" v-if="selectedLog.actor_email">{{ selectedLog.actor_email }}</div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Room</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">
                  {{ selectedLog.room_type || 'N/A' }}
                  <span v-if="selectedLog.room_number" class="text-gold">#{{ selectedLog.room_number }}</span>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Guests Count</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">
                  {{ selectedLog.guests_count }} {{ selectedLog.guests_count === 1 ? 'Guest' : 'Guests' }}
                  ({{ selectedLog.adults_count }} Adult{{ selectedLog.adults_count === 1 ? '' : 's' }}<template v-if="selectedLog.children_count > 0">, {{ selectedLog.children_count }} Child{{ selectedLog.children_count === 1 ? '' : 'ren' }}</template>)
                </div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Guest Details</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">{{ selectedLog.guest_name || '—' }}</div>
                <div class="text-muted" style="font-size: 0.74rem;" v-if="selectedLog.guest_email">{{ selectedLog.guest_email }}</div>
                <div class="text-muted" style="font-size: 0.74rem;" v-if="selectedLog.guest_phone && selectedLog.guest_phone !== 'N/A'">{{ selectedLog.guest_phone }}</div>
              </div>

              <div class="col-sm-6">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Booking Reference &amp; Amount</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">
                  <span v-if="selectedLog.entity_id">#RES-{{ String(selectedLog.entity_id).padStart(3, '0') }}</span>
                  <span v-else>—</span>
                  <span v-if="selectedLog.total_amount !== null" class="ms-2 text-gold">₱{{ formatAmount(selectedLog.total_amount) }}</span>
                </div>
                <div v-if="selectedLog.payment_status" class="mt-1">
                  <span class="badge rounded-pill text-uppercase" :class="paymentBadgeClass(selectedLog.payment_status)" style="font-size: 0.6rem;">
                    {{ formatActionLabel(selectedLog.payment_status) }}
                  </span>
                </div>
              </div>

              <div class="col-sm-6" v-if="selectedLog.check_in">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Check-in</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">{{ formatDate(selectedLog.check_in) }} · {{ formatTime(selectedLog.check_in) }}</div>
              </div>

              <div class="col-sm-6" v-if="selectedLog.check_out">
                <div class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">Check-out</div>
                <div class="fw-semibold text-secondary-dark mt-0.5">{{ formatDate(selectedLog.check_out) }} · {{ formatTime(selectedLog.check_out) }}</div>
              </div>

              <div class="col-12" v-if="selectedLog.old_values || selectedLog.new_values">
                <div class="border-top pt-3 mt-1 row g-3">
                  <div class="col-sm-6" v-if="selectedLog.old_values">
                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.68rem;">Previous Values</div>
                    <pre class="bg-light p-2.5 rounded-3 mb-0 small" style="font-size: 0.72rem; white-space: pre-wrap;">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
                  </div>
                  <div class="col-sm-6" v-if="selectedLog.new_values">
                    <div class="text-muted text-uppercase fw-bold mb-1" style="font-size: 0.68rem;">Updated Values</div>
                    <pre class="bg-light p-2.5 rounded-3 mb-0 small" style="font-size: 0.72rem; white-space: pre-wrap;">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="px-4 py-3 border-top bg-light-subtle text-end">
            <button class="btn btn-sm btn-secondary rounded-pill px-4" @click="selectedLog = null">Close</button>
          </div>
        </div>
      </div>
    </teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useAuth } from '../../store/auth';
import AdminPagination from '../../components/AdminPagination.vue';

const { state } = useAuth();

const loading = ref(true);
const exporting = ref(false);
const printing = ref(false);
const activeTab = ref('logs');
const selectedLog = ref(null);

const logs = ref([]);
const rooms = ref([]);
const roomSummary = ref([]);

const summary = ref({
  total_activities: 0,
  total_bookings: 0,
  total_guests_booked: 0,
  total_adults_booked: 0,
  total_children_booked: 0,
  booked_by_guest: 0,
  booked_by_staff: 0,
  booked_by_admin: 0,
  booked_by_staff_total: 0,
});

const pagination = ref({
  current_page: 1,
  per_page: 15,
  total: 0,
});

const filters = ref({
  search: '',
  actor_role: 'all',
  action: 'all',
  room_id: 'all',
  sort: 'latest',
  start_date: '',
  end_date: '',
});

const hasActiveFilters = computed(() => {
  return Boolean(
    filters.value.search ||
    filters.value.actor_role !== 'all' ||
    filters.value.action !== 'all' ||
    filters.value.room_id !== 'all' ||
    filters.value.sort !== 'latest' ||
    filters.value.start_date ||
    filters.value.end_date
  );
});

let debounceTimer = null;
const debouncedFetch = () => {
  if (debounceTimer) clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    pagination.value.current_page = 1;
    fetchAuditLogs();
  }, 300);
};

const applyFilters = () => {
  pagination.value.current_page = 1;
  fetchAuditLogs();
};

const clearFilters = () => {
  filters.value = {
    search: '',
    actor_role: 'all',
    action: 'all',
    room_id: 'all',
    sort: 'latest',
    start_date: '',
    end_date: '',
  };
  pagination.value.current_page = 1;
  fetchAuditLogs();
};

const filterByRoom = (roomId) => {
  filters.value.room_id = roomId;
  activeTab.value = 'logs';
  pagination.value.current_page = 1;
  fetchAuditLogs();
};

const handlePageChange = (page) => {
  pagination.value.current_page = page;
  fetchAuditLogs();
};

const handlePageSizeChange = (size) => {
  pagination.value.per_page = size;
  pagination.value.current_page = 1;
  fetchAuditLogs();
};

const buildParams = () => {
  const params = {
    page: pagination.value.current_page,
    per_page: pagination.value.per_page,
    sort: filters.value.sort,
  };
  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.actor_role !== 'all') params.actor_role = filters.value.actor_role;
  if (filters.value.action !== 'all') params.action = filters.value.action;
  if (filters.value.room_id !== 'all') params.room_id = filters.value.room_id;
  if (filters.value.start_date) params.start_date = filters.value.start_date;
  if (filters.value.end_date) params.end_date = filters.value.end_date;
  return params;
};

const fetchAuditLogs = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/audit-logs', {
      params: buildParams(),
    });
    const data = response.data;
    logs.value = data.logs?.data || [];
    pagination.value = {
      current_page: data.logs?.current_page || 1,
      per_page: data.logs?.per_page || 15,
      total: data.logs?.total || 0,
    };
    if (data.summary) summary.value = data.summary;
    roomSummary.value = data.room_summary || [];
    rooms.value = data.rooms || [];
  } catch (err) {
    console.error('Failed to fetch audit logs:', err);
  } finally {
    loading.value = false;
  }
};

const exportCsv = async () => {
  exporting.value = true;
  try {
    const response = await axios.get('/api/admin/audit-logs/export', {
      params: buildParams(),
      responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Failed to export audit logs:', err);
  } finally {
    exporting.value = false;
  }
};

const escapeHtml = (val) => {
  return String(val ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
};

const currentAdminName = computed(() => {
  if (!state.user) return 'Administrator';
  const name = `${state.user.first_name || ''} ${state.user.last_name || ''}`.trim();
  return name || state.user.name || 'Administrator';
});

const printReport = async () => {
  printing.value = true;
  try {
    const response = await axios.get('/api/admin/audit-logs', {
      params: {
        ...buildParams(),
        page: 1,
        per_page: 1000,
        all: 1,
      },
    });

    const data = response.data;
    const allLogs = data.logs?.data || [];
    const printSummary = data.summary || summary.value;
    const printRooms = data.room_summary || roomSummary.value;

    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      Swal.fire({
        icon: 'warning',
        title: 'Pop-up Blocked',
        text: 'Please allow pop-ups for this site to print the audit log report.',
        confirmButtonColor: '#BC9151',
      });
      return;
    }

    const generatedAt = new Date().toLocaleDateString('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });

    const activeFilterParts = [];
    if (filters.value.actor_role !== 'all') activeFilterParts.push(`Role: ${filters.value.actor_role.replace(/_/g, ' ').toUpperCase()}`);
    if (filters.value.action !== 'all') activeFilterParts.push(`Activity: ${formatActionLabel(filters.value.action).toUpperCase()}`);
    if (filters.value.room_id !== 'all') {
      const r = rooms.value.find(rm => String(rm.id) === String(filters.value.room_id));
      if (r) activeFilterParts.push(`Room: ${r.room_type} #${r.room_number}`);
    }
    if (filters.value.start_date || filters.value.end_date) {
      activeFilterParts.push(`Date: ${filters.value.start_date || 'Start'} to ${filters.value.end_date || 'Present'}`);
    }
    if (filters.value.search) activeFilterParts.push(`Search: "${filters.value.search}"`);
    const filterSummaryText = activeFilterParts.length > 0 ? activeFilterParts.join(' • ') : 'All Records';

    const roomRows = printRooms.map(r => {
      const totalGuests = Number(r.total_guests || 0);
      const guestsText = totalGuests > 0
        ? `${totalGuests} (${Number(r.total_adults || 0)}A${Number(r.total_children || 0) > 0 ? `, ${Number(r.total_children)}C` : ''})`
        : '0';
      const lastBookedText = r.last_booked_at
        ? `${escapeHtml(formatDate(r.last_booked_at))} ${escapeHtml(formatTime(r.last_booked_at))}`
        : '<span style="color: #94a3b8;">No bookings yet</span>';
      return `
      <tr>
        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;"><strong>${escapeHtml(r.room_type)}</strong> <span style="color: #9a7640;">#${escapeHtml(r.room_number)}</span></td>
        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: center;">${Number(r.bookings_count || 0)}</td>
        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: center; font-weight: bold;">${guestsText}</td>
        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: center;">${Number(r.guest_bookings || 0)}</td>
        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: center;">${Number(r.staff_bookings || 0)}</td>
        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">${lastBookedText}</td>
      </tr>
    `;
    }).join('');

    const logRows = allLogs.map(log => {
      const ts = log.updated_at || log.created_at;
      const refStr = log.entity_id ? `#RES-${String(log.entity_id).padStart(3, '0')}` : '—';
      const stayStr = (log.check_in && log.check_out) ? `${formatShortDate(log.check_in)} – ${formatShortDate(log.check_out)}` : '—';
      const amtStr = (log.total_amount !== null && log.total_amount !== undefined) ? `₱${formatAmount(log.total_amount)}` : '—';
      const guestBreakdown = `${log.guests_count || 1} (${log.adults_count || 1}A${log.children_count > 0 ? `, ${log.children_count}C` : ''})`;
      return `
        <tr>
          <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; white-space: nowrap;">
            <div style="font-weight: bold; color: #0f172a;">${escapeHtml(formatDate(ts))}</div>
            <div style="font-size: 9px; color: #64748b;">${escapeHtml(formatTime(ts))}</div>
          </td>
          <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; white-space: nowrap;">
            <div style="font-weight: bold; text-transform: uppercase; font-size: 9px;">${escapeHtml(formatActionLabel(log.action))}</div>
            <div style="font-size: 9px; color: #64748b;">${escapeHtml(refStr)}</div>
          </td>
          <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">
            <div style="font-weight: bold;">${escapeHtml(log.actor_name || 'System')} <span style="font-size: 8px; text-transform: uppercase; color: #9a7640;">[${escapeHtml(log.actor_role || 'guest')}]</span></div>
            ${log.actor_email ? `<div style="font-size: 9px; color: #64748b;">${escapeHtml(log.actor_email)}</div>` : ''}
          </td>
          <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">
            <div><strong>${escapeHtml(log.room_type || 'Room')}</strong>${log.room_number ? ` #${escapeHtml(log.room_number)}` : ''}</div>
            <div style="font-size: 9px; color: #64748b;">Guests: ${escapeHtml(guestBreakdown)}</div>
          </td>
          <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">
            <div style="font-weight: 600;">${escapeHtml(log.guest_name || '—')}</div>
            <div style="font-size: 9px; color: #64748b;">${escapeHtml(stayStr)}</div>
          </td>
          <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: right; white-space: nowrap;">
            <div style="font-weight: bold;">${escapeHtml(amtStr)}</div>
            ${log.payment_status ? `<div style="font-size: 8px; text-transform: uppercase; color: #64748b;">${escapeHtml(formatActionLabel(log.payment_status))}</div>` : ''}
          </td>
        </tr>
      `;
    }).join('');

    const html = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>EME'S Apartelle — Audit Log Report</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    body { font-family: Arial, Helvetica, sans-serif; color: #1e293b; margin: 0; padding: 20px; background: #fff; font-size: 11px; line-height: 1.4; }
    .no-print { background: #1e293b; color: #fff; padding: 10px 18px; border-radius: 8px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; }
    .btn-action { background: #bc9151; color: #fff; border: none; padding: 7px 16px; border-radius: 6px; font-weight: bold; font-size: 12px; cursor: pointer; }
    .btn-close { background: #475569; color: #fff; border: none; padding: 7px 14px; border-radius: 6px; font-size: 12px; cursor: pointer; margin-left: 8px; }
    .header-table { width: 100%; border-bottom: 2px solid #bc9151; padding-bottom: 10px; margin-bottom: 16px; }
    .brand-title { font-size: 20px; font-weight: bold; color: #0f172a; margin: 0; letter-spacing: 0.5px; }
    .brand-subtitle { font-size: 10px; color: #64748b; margin: 2px 0 0 0; text-transform: uppercase; }
    .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 16px; }
    .kpi-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 3px solid #bc9151; border-radius: 6px; padding: 10px; text-align: center; width: 25%; }
    .kpi-label { font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 3px; }
    .kpi-value { font-size: 16px; font-weight: bold; color: #0f172a; }
    .kpi-sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .section-title { font-size: 11px; font-weight: bold; color: #0f172a; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin: 16px 0 8px 0; }
    table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
    table.data-table th { background: #0f172a; color: #fff; font-size: 9px; font-weight: bold; text-transform: uppercase; padding: 7px 8px; text-align: left; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .footer-note { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
    @media print { .no-print { display: none !important; } body { padding: 0 !important; } }
  </style>
</head>
<body>
  <div class="no-print">
    <div><strong>EME'S Apartelle</strong> • Audit Log Print Layout</div>
    <div>
      <button class="btn-action" onclick="window.print()">Print / Save as PDF</button>
      <button class="btn-close" onclick="window.close()">Close</button>
    </div>
  </div>

  <table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
      <td>
        <h1 class="brand-title">EME'S APARTELLE</h1>
        <p class="brand-subtitle">Booking Activity Audit Log Report</p>
      </td>
      <td style="text-align: right; font-size: 10px; color: #475569;">
        <div><strong>Scope:</strong> ${escapeHtml(filterSummaryText)}</div>
        <div><strong>Generated:</strong> ${escapeHtml(generatedAt)}</div>
        <div><strong>Prepared By:</strong> ${escapeHtml(currentAdminName.value)}</div>
      </td>
    </tr>
  </table>

  <table class="kpi-table" cellpadding="0" cellspacing="0">
    <tr>
      <td class="kpi-cell">
        <div class="kpi-label">Total Bookings</div>
        <div class="kpi-value" style="color: #bc9151;">${Number(printSummary.total_bookings || 0).toLocaleString()}</div>
        <div class="kpi-sub">${Number(printSummary.total_activities || 0).toLocaleString()} total activities</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Total Guests Booked</div>
        <div class="kpi-value">${Number(printSummary.total_guests_booked || 0).toLocaleString()}</div>
        <div class="kpi-sub">${Number(printSummary.total_adults_booked || 0)} adults${Number(printSummary.total_children_booked || 0) > 0 ? `, ${Number(printSummary.total_children_booked)} children` : ''}</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Booked by Guests</div>
        <div class="kpi-value">${Number(printSummary.booked_by_guest || 0).toLocaleString()}</div>
        <div class="kpi-sub">Self-service bookings</div>
      </td>
      <td class="kpi-cell">
        <div class="kpi-label">Booked by Staff / Admin</div>
        <div class="kpi-value">${Number(printSummary.booked_by_staff_total || 0).toLocaleString()}</div>
        <div class="kpi-sub">${Number(printSummary.booked_by_staff || 0)} staff · ${Number(printSummary.booked_by_admin || 0)} admin</div>
      </td>
    </tr>
  </table>

  ${printRooms.length > 0 ? `
  <div class="section-title">Room Booking Summary (${printRooms.length} Rooms)</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Room</th>
        <th style="text-align: center;">Bookings</th>
        <th style="text-align: center;">Total Guests</th>
        <th style="text-align: center;">By Guest</th>
        <th style="text-align: center;">By Staff / Admin</th>
        <th>Last Booking</th>
      </tr>
    </thead>
    <tbody>${roomRows}</tbody>
  </table>
  ` : ''}

  <div class="section-title">Booking Activity Log (${allLogs.length} Entries)</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Date &amp; Time</th>
        <th>Activity</th>
        <th>Booked / Performed By</th>
        <th>Room &amp; Guests</th>
        <th>Guest &amp; Stay</th>
        <th style="text-align: right;">Amount</th>
      </tr>
    </thead>
    <tbody>
      ${logRows || '<tr><td colspan="6" style="padding: 14px; text-align: center; color: #64748b;">No audit log entries match the selected filters.</td></tr>'}
    </tbody>
  </table>

  <div class="footer-note">
    EME'S Apartelle • Booking Activity Audit Log Report • Confidential Document for Internal Use Only
  </div>
</body>
</html>
    `;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
    }, 300);
  } catch (err) {
    console.error('Failed to generate printable audit report:', err);
    Swal.fire({
      icon: 'error',
      title: 'Print Failed',
      text: 'Unable to load audit log data for printing.',
      confirmButtonColor: '#BC9151',
    });
  } finally {
    printing.value = false;
  }
};

const openDetailModal = (log) => {
  selectedLog.value = log;
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatShortDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

const formatTime = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (Number.isNaN(d.getTime())) return '';
  return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
};

const formatAmount = (amount) => {
  const num = parseFloat(amount);
  if (Number.isNaN(num)) return '0.00';
  return num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatActionLabel = (action) => {
  if (!action) return 'Activity';
  return action.replace(/_/g, ' ');
};

const actionBadgeClass = (action) => {
  switch (action) {
    case 'booking_created':
      return 'bg-gold-subtle text-gold';
    case 'booking_confirmed':
      return 'bg-success-subtle text-success';
    case 'check_in':
      return 'bg-info-subtle text-info-emphasis';
    case 'check_out':
      return 'bg-primary-subtle text-primary';
    case 'booking_cancelled':
    case 'booking_deleted':
      return 'bg-danger-subtle text-danger';
    case 'booking_rescheduled':
    case 'booking_updated':
    case 'payment_updated':
      return 'bg-warning-subtle text-warning-emphasis';
    default:
      return 'bg-secondary-subtle text-secondary';
  }
};

const roleBadgeClass = (role) => {
  switch ((role || '').toLowerCase()) {
    case 'admin':
      return 'bg-dark text-white';
    case 'staff':
      return 'bg-primary-subtle text-primary';
    default:
      return 'bg-success-subtle text-success';
  }
};

const roleAvatarClass = (role) => {
  switch ((role || '').toLowerCase()) {
    case 'admin':
      return 'bg-dark text-white';
    case 'staff':
      return 'bg-primary-subtle text-primary';
    default:
      return 'bg-gold-subtle text-gold';
  }
};

const paymentBadgeClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'paid':
      return 'bg-success-subtle text-success';
    case 'partially_paid':
      return 'bg-warning-subtle text-warning-emphasis';
    case 'refunded':
      return 'bg-info-subtle text-info-emphasis';
    default:
      return 'bg-secondary-subtle text-secondary';
  }
};

onMounted(() => {
  fetchAuditLogs();
});
</script>

<style scoped>
.stats-card-premium {
  padding: 1.4rem;
  border-radius: 1rem;
  box-shadow: 0 4px 20px rgba(26, 38, 52, 0.05);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stats-card-premium:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(26, 38, 52, 0.08);
}

.stats-icon-premium {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  flex-shrink: 0;
}

.bg-gold-subtle {
  background-color: rgba(188, 145, 81, 0.14);
}

.text-gold {
  color: #BC9151;
}

.btn-gold {
  background: linear-gradient(135deg, #BC9151 0%, #9A7640 100%);
  color: #fff;
  border: none;
}

.btn-outline-gold {
  color: #9A7640;
  border: 1px solid #BC9151;
  background: transparent;
}

.btn-outline-gold:hover {
  background: #BC9151;
  color: #fff;
}

.btn-action-view {
  width: 30px;
  height: 30px;
  padding: 0;
  border-radius: 8px;
  background: rgba(188, 145, 81, 0.12);
  color: #9A7640;
  border: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.btn-action-view:hover {
  background: #BC9151;
  color: #fff;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
}

.tracking-wider {
  letter-spacing: 0.05em;
}
</style>

