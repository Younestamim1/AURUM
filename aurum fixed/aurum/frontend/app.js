/* ═══════════════════════════════════════════════
   AURUM — app.js
   API_BASE is declared in config.js (loaded before this script)
═══════════════════════════════════════════════ */

/* ══════════ THEME ══════════ */
const body        = document.body;
const themeToggle = document.getElementById('themeToggle');
const themeIcon   = document.getElementById('themeIcon');

const savedTheme  = localStorage.getItem('aurum-theme') || 'dark-mode';
body.className    = savedTheme;
setThemeIcon(savedTheme);

themeToggle.addEventListener('click', () => {
  const isDark = body.classList.contains('dark-mode');
  const next   = isDark ? 'light-mode' : 'dark-mode';
  body.className = next;
  localStorage.setItem('aurum-theme', next);
  setThemeIcon(next);
});

function setThemeIcon(mode) {
  if (themeIcon) themeIcon.textContent = mode === 'dark-mode' ? '☀' : '☾';
}

/* ══════════ SESSION ══════════ */
const navUser       = document.getElementById('navUser');
const navUserLogged = document.getElementById('navUserLogged');
const navAvatar     = document.getElementById('navAvatar');
const navUsername   = document.getElementById('navUsername');

const savedUser = JSON.parse(localStorage.getItem('aurum-user') || 'null');
if (savedUser) {
  if (navUser)       navUser.style.display = 'none';
  if (navUserLogged) navUserLogged.classList.remove('hidden');
  if (navAvatar)     navAvatar.textContent   = savedUser.initials || '??';
  if (navUsername)   navUsername.textContent = (savedUser.name || '').split(' ')[0];
}

document.getElementById('navSignout')?.addEventListener('click', () => {
  localStorage.removeItem('aurum-user');
  localStorage.removeItem('aurum-token');
  if (navUserLogged) navUserLogged.classList.add('hidden');
  if (navUser)       navUser.style.display = '';
  showToast('You have been signed out.');
});

/* ══════════ NAV ══════════ */
const navbar   = document.getElementById('navbar');
const pages    = document.querySelectorAll('.page');
const navLinks = document.querySelectorAll('.nav-link');

window.addEventListener('scroll', () => navbar?.classList.toggle('scrolled', window.scrollY > 40));

function showPage(id) {
  pages.forEach(p => p.classList.remove('active'));
  navLinks.forEach(l => l.classList.remove('active'));
  const target = document.getElementById('page-' + id);
  if (target) {
    target.classList.add('active');
    const offset = (navbar?.offsetHeight || 0) + 8;
    window.scrollTo({ top: offset, behavior: 'smooth' });
  }
  navLinks.forEach(l => { if (l.dataset.page === id) l.classList.add('active'); });
  document.querySelector('.nav-links')?.classList.remove('mobile-open');
}

navLinks.forEach(link => {
  link.addEventListener('click', e => {
    if (link.dataset.page) { e.preventDefault(); showPage(link.dataset.page); return; }
    if (link.getAttribute('href') === 'owner.html') {
      e.preventDefault();
      const curUser = JSON.parse(localStorage.getItem('aurum-user') || 'null');
      if (!curUser || curUser.role !== 'owner') {
        showSideSigninTip(link, null, 'Sign in as an owner to list your property');
      } else {
        window.location.href = 'owner.html';
      }
    }
  });
});

/* ══════════ HOTEL DATABASE (loaded from API) ══════════ */
let hotelDatabase = [];

async function loadHotelsFromAPI() {
  try {
    const res  = await fetch(`${API_BASE}/hotels`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.success && Array.isArray(data.data) && data.data.length) {
      // Normalise API response to match frontend field names
      hotelDatabase = data.data.map(h => ({
        id:          h.hotel_id  || h.id,
        name:        h.name,
        city:        h.city,
        country:     h.country,
        stars:       h.stars        || 5,
        price:       parseFloat(h.price) || 0,
        rating:      parseFloat(h.rating) || 0,
        reviews:     parseInt(h.reviews) || 0,
        desc:        h.description || '',
        amenities:   Array.isArray(h.amenities) ? h.amenities : (h.amenities || '').split(',').filter(Boolean),
        initial:     h.initial     || h.name.slice(0, 2).toUpperCase(),
        color:       h.color       || '#1a1208',
        maxChildren: parseInt(h.max_children) || 4,
        rooms:       parseInt(h.total_rooms)  || 10,
        photos:      makePhotos(
          h.initial || h.name.slice(0, 2).toUpperCase(),
          h.color   || '#1a1208',
          adjustColor(h.color || '#1a1208', 20),
          adjustColor(h.color || '#1a1208', -20)
        ),
      }));
      renderResults(filterHotels('Paris', 1, 0, 'any'), 'Paris', 1, 0, 'any');
      return;
    }
    throw new Error('Empty hotel list from API');
  } catch (e) {
    console.warn('API hotels failed, using local fallback:', e.message);
    useLocalHotelDatabase();
  }
}

function adjustColor(hex, amount) {
  try {
    const n = parseInt(hex.replace('#',''), 16);
    const r = Math.min(255, Math.max(0, (n >> 16) + amount));
    const g = Math.min(255, Math.max(0, ((n >> 8) & 0xFF) + amount));
    const b = Math.min(255, Math.max(0, (n & 0xFF) + amount));
    return '#' + [r,g,b].map(x => x.toString(16).padStart(2,'0')).join('');
  } catch(e) { return hex; }
}

function useLocalHotelDatabase() {
  hotelDatabase = [
    { id:1,  name:'Le Grand Hôtel',         city:'Paris',     country:'France',  stars:5, price:450,  rating:4.90, reviews:1284, desc:'Belle Époque grandeur at the heart of Paris.',           amenities:['Wi-Fi','Spa','Restaurant','Concierge','Bar'],             initial:'LG', color:'#1a1208', maxChildren:4, rooms:3,  photos: makePhotos('LG','#1a1208','#2a1f0a','#180e04') },
    { id:2,  name:'Hôtel de Crillon',        city:'Paris',     country:'France',  stars:5, price:980,  rating:4.95, reviews:876,  desc:'A palatial 18th-century landmark on Place de la Concorde.', amenities:['Wi-Fi','Pool','Spa','Restaurant','Concierge'],           initial:'HC', color:'#14100a', maxChildren:2, rooms:5,  photos: makePhotos('HC','#14100a','#201808','#0e0c06') },
    { id:3,  name:'Burj Al Arab',            city:'Dubai',     country:'UAE',     stars:5, price:1800, rating:4.85, reviews:2341, desc:"The world's most iconic sail-shaped hotel.",              amenities:['Pool','Spa','Restaurant','Bar','Transfer','Concierge'],  initial:'BA', color:'#0a1218', maxChildren:3, rooms:2,  photos: makePhotos('BA','#0a1218','#0d1e2e','#06101a') },
    { id:4,  name:'Atlantis The Palm',       city:'Dubai',     country:'UAE',     stars:5, price:620,  rating:4.70, reviews:985,  desc:'A waterpark resort on the Palm Jumeirah.',               amenities:['Pool','Spa','Waterpark','Restaurant','Bar'],              initial:'AP', color:'#0d1e2e', maxChildren:4, rooms:10, photos: makePhotos('AP','#0d1e2e','#0a1520','#0f2a3e') },
    { id:5,  name:'The Peninsula',           city:'Tokyo',     country:'Japan',   stars:5, price:720,  rating:4.90, reviews:998,  desc:'Eastern refinement in the heart of Tokyo.',             amenities:['Spa','Pool','Restaurant','Concierge'],                    initial:'TP', color:'#120a10', maxChildren:2, rooms:4,  photos: makePhotos('TP','#120a10','#1e0f1a','#0c0608') },
    { id:6,  name:'Sofitel Algiers',         city:'Algiers',   country:'Algeria', stars:5, price:220,  rating:4.72, reviews:642,  desc:'French elegance in the Algerian capital.',              amenities:['Pool','Spa','Restaurant'],                                initial:'SA', color:'#0a1a0e', maxChildren:3, rooms:4,  photos: makePhotos('SA','#0a1a0e','#0e2614','#060e08') },
    { id:7,  name:'El Djazair Hotel',        city:'Algiers',   country:'Algeria', stars:5, price:180,  rating:4.65, reviews:430,  desc:'A colonial-era landmark in Algiers.',                   amenities:['Pool','Restaurant','Bar'],                                initial:'EJ', color:'#0e1a0a', maxChildren:4, rooms:3,  photos: makePhotos('EJ','#0e1a0a','#162610','#080e06') },
    { id:8,  name:'Four Seasons Bosphorus',  city:'Istanbul',  country:'Turkey',  stars:5, price:680,  rating:4.91, reviews:774,  desc:'An Ottoman palace on the Bosphorus strait.',            amenities:['Spa','Pool','Restaurant','Concierge'],                    initial:'FS', color:'#1a0a08', maxChildren:2, rooms:6,  photos: makePhotos('FS','#1a0a08','#2a1210','#0e0604') },
    { id:9,  name:'La Mamounia',             city:'Marrakech', country:'Morocco', stars:5, price:750,  rating:4.94, reviews:512,  desc:'Moorish splendour surrounded by gardens.',              amenities:['Pool','Spa','Restaurant','Bar'],                          initial:'LM', color:'#1a0e06', maxChildren:3, rooms:8,  photos: makePhotos('LM','#1a0e06','#261508','#0e0802') },
    { id:10, name:'Hotel Arts Barcelona',    city:'Barcelona', country:'Spain',   stars:5, price:480,  rating:4.75, reviews:863,  desc:'A beachfront masterpiece in Barcelona.',                amenities:['Pool','Spa','Restaurant','Bar','Concierge'],              initial:'HB', color:'#0a0e1a', maxChildren:3, rooms:7,  photos: makePhotos('HB','#0a0e1a','#0d1526','#06080e') },
  ];
  renderResults(filterHotels('Paris', 1, 0, 'any'), 'Paris', 1, 0, 'any');
}

function makePhotos(initial, c1, c2, c3) {
  return {
    hotel: [
      { gradient: `linear-gradient(135deg,${c1},${c2})`, label: 'Exterior',          initial },
      { gradient: `linear-gradient(160deg,${c2},${c3})`, label: 'Lobby',             initial },
      { gradient: `linear-gradient(120deg,${c1},${c3})`, label: 'Terrace',           initial },
      { gradient: `linear-gradient(180deg,${c3},${c2})`, label: 'Garden / Courtyard',initial },
    ],
    rooms: [
      { gradient: `linear-gradient(140deg,${c1},${c3})`, label: 'Deluxe Room',       initial },
      { gradient: `linear-gradient(160deg,${c2},${c1})`, label: 'Suite',             initial },
      { gradient: `linear-gradient(120deg,${c3},${c2})`, label: 'Grand Suite',       initial },
      { gradient: `linear-gradient(180deg,${c1},${c2})`, label: 'Presidential Suite',initial },
    ],
    amenities: [
      { gradient: `linear-gradient(135deg,${c2},${c3})`, label: 'Swimming Pool',     initial },
      { gradient: `linear-gradient(150deg,${c1},${c2})`, label: 'Spa & Wellness',    initial },
      { gradient: `linear-gradient(125deg,${c3},${c1})`, label: 'Restaurant',        initial },
      { gradient: `linear-gradient(165deg,${c2},${c1})`, label: 'Bar & Lounge',      initial },
    ],
  };
}

/* ══════════ CUSTOM DROPDOWNS ══════════ */
function initCustomSelect(id, hiddenSelectId) {
  const container = document.getElementById(id);
  const hiddenSel = document.getElementById(hiddenSelectId);
  if (!container || !hiddenSel) return;
  const trigger   = container.querySelector('.custom-select-trigger');
  const valueSpan = container.querySelector('.custom-select-value');
  const options   = container.querySelectorAll('.custom-select-option');

  trigger.addEventListener('click', e => {
    e.stopPropagation();
    document.querySelectorAll('.custom-select.open').forEach(el => { if (el !== container) el.classList.remove('open'); });
    container.classList.toggle('open');
  });
  options.forEach(opt => {
    opt.addEventListener('click', () => {
      hiddenSel.value       = opt.dataset.value;
      valueSpan.textContent = opt.textContent;
      options.forEach(o => o.classList.remove('selected'));
      opt.classList.add('selected');
      container.classList.remove('open');
    });
  });
  document.addEventListener('click', e => { if (!container.contains(e.target)) container.classList.remove('open'); });
}
initCustomSelect('roomsSelect',    's-rooms');
initCustomSelect('childrenSelect', 's-children');
initCustomSelect('budgetSelect',   's-price');

/* ══════════ SEARCH ══════════ */
document.getElementById('searchBtn').addEventListener('click', () => {
  const location = document.getElementById('s-location').value.trim();
  const rooms    = parseInt(document.getElementById('s-rooms').value)    || 1;
  const children = parseInt(document.getElementById('s-children').value) || 0;
  const price    = document.getElementById('s-price').value;
  if (!location) { showToast('Please enter a destination.', 'error'); return; }
  renderResults(filterHotels(location, rooms, children, price), location, rooms, children, price);
  showPage('results');
});

function filterHotels(loc, rooms, children, price) {
  return hotelDatabase.filter(h => {
    const lm = !loc || h.city.toLowerCase().includes(loc.toLowerCase()) || h.country.toLowerCase().includes(loc.toLowerCase());
    const rm = (h.rooms || 10) >= rooms;
    const cm = (h.maxChildren || 4) >= children;
    let   pm = true;
    if (price !== 'any') {
      const max = parseInt(price);
      pm = price === '1001' ? h.price > 1000 : h.price <= max;
    }
    return lm && rm && cm && pm;
  });
}

function renderResults(hotels, loc, rooms, children, price) {
  const grid  = document.getElementById('resultsGrid');
  const title = document.getElementById('resultsTitle');
  const meta  = document.getElementById('resultsMeta');
  const pl    = price === 'any' ? 'Any budget' : price === '1001' ? 'Over $1,000/night' : `Up to $${price}/night`;

  title.innerHTML  = `Hotels in <em>${loc || 'All Destinations'}</em>`;
  meta.textContent = `Showing ${hotels.length} propert${hotels.length === 1 ? 'y' : 'ies'} · ${rooms} room${rooms > 1 ? 's' : ''} · ${children} child${children !== 1 ? 'ren' : ''} · ${pl}`;
  grid.innerHTML   = '';

  if (!hotels.length) {
    grid.innerHTML = `<div class="no-results">No properties found.<br/><small style="font-size:16px;color:var(--text-m)">Try adjusting your filters.</small></div>`;
    return;
  }
  hotels.forEach((h, i) => grid.appendChild(createHotelCard(h, i)));

  document.getElementById('sortFilter').onchange = function () {
    const s = [...hotels];
    if (this.value === 'price-asc')  s.sort((a, b) => a.price - b.price);
    if (this.value === 'price-desc') s.sort((a, b) => b.price - a.price);
    if (this.value === 'rating')     s.sort((a, b) => b.rating - a.rating);
    grid.innerHTML = '';
    s.forEach((h, i) => grid.appendChild(createHotelCard(h, i)));
  };
}

function createHotelCard(hotel, delay = 0) {
  const card   = document.createElement('div');
  card.className = 'hotel-card';
  card.style.cssText = `animation:fadeUp 0.5s ease ${delay * 0.07}s both`;
  const stars  = '★'.repeat(hotel.stars) + '☆'.repeat(5 - hotel.stars);
  card.innerHTML = `
    <div class="hotel-card-img" style="background:linear-gradient(135deg,${hotel.color},#1a1a10)">
      <div class="hotel-card-img-inner">${hotel.initial}</div>
      <div class="hotel-badge">${hotel.stars} ★</div>
      <button class="hotel-view-photos">📷 View Photos</button>
    </div>
    <div class="hotel-card-body">
      <div class="hotel-card-name">${hotel.name}</div>
      <div class="hotel-card-location">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        ${hotel.city}, ${hotel.country}
      </div>
      <div class="hotel-card-desc">${hotel.desc}</div>
      <div class="hotel-card-amenities">${hotel.amenities.slice(0, 4).map(a => `<span class="amenity-tag">${a}</span>`).join('')}</div>
      <div class="hotel-card-footer">
        <div>
          <span class="price-from">from</span>
          <span class="price-num">$${hotel.price}</span>
          <span class="price-per">/night</span>
        </div>
        <div style="text-align:right">
          <span class="stars">${stars}</span>
          <span class="rating-count">${hotel.rating} (${hotel.reviews.toLocaleString()})</span>
        </div>
      </div>
      <button class="hotel-book-btn">Reserve Now</button>
    </div>`;

  card.querySelector('.hotel-view-photos').addEventListener('click', e => { e.stopPropagation(); openGallery(hotel); });
  card.querySelector('.hotel-card-img-inner').addEventListener('click', () => openGallery(hotel));
  card.querySelector('.hotel-book-btn').addEventListener('click', e => {
    e.stopPropagation();
    const curUser = JSON.parse(localStorage.getItem('aurum-user') || 'null');
    if (curUser) openBookingModal(hotel);
    else         showSideSigninTip(e.target, hotel);
  });
  return card;
}

/* Featured clicks */
document.querySelectorAll('.featured-card').forEach(card => {
  card.addEventListener('click', () => {
    const dest = card.dataset.dest;
    document.getElementById('s-location').value = dest;
    renderResults(filterHotels(dest, 1, 0, 'any'), dest, 1, 0, 'any');
    showPage('results');
  });
});

/* ══════════ GALLERY MODAL ══════════ */
let galHotel = null, galTab = 'hotel', galIndex = 0;
const galleryModal    = document.getElementById('galleryModal');
const galleryBackdrop = document.getElementById('galleryBackdrop');
const galleryClose    = document.getElementById('galleryClose');
const galImgInner     = document.getElementById('galImgInner');
const galImgLabel     = document.getElementById('galImgLabel');
const galleryThumbs   = document.getElementById('galleryThumbs');
const galPrev         = document.getElementById('galPrev');
const galNext         = document.getElementById('galNext');

function openGallery(hotel) {
  galHotel = hotel; galTab = 'hotel'; galIndex = 0;
  document.getElementById('galleryHotelName').textContent = hotel.name;
  document.getElementById('galleryHotelLoc').textContent  = `${hotel.city}, ${hotel.country}`;
  document.getElementById('galPrice').textContent         = `$${hotel.price}`;
  document.querySelectorAll('.gtab').forEach(t => t.classList.remove('active'));
  document.querySelector('.gtab[data-tab="hotel"]').classList.add('active');
  renderGallery();
  galleryModal.classList.add('open');
  document.body.style.overflow = 'hidden';
  document.getElementById('galBookBtn').onclick = () => {
    const curUser = JSON.parse(localStorage.getItem('aurum-user') || 'null');
    if (curUser) { closeGallery(); setTimeout(() => openBookingModal(hotel), 200); }
    else         showSideSigninTip(document.getElementById('galBookBtn'), hotel);
  };
}

function renderGallery() {
  if (!galHotel?.photos?.[galTab]) return;
  const photos = galHotel.photos[galTab];
  renderMainPhoto(photos[galIndex]);
  renderThumbs(photos);
}

function renderMainPhoto(photo) {
  galImgInner.style.background   = photo.gradient;
  galImgInner.style.backgroundSize = 'cover';
  galImgInner.textContent        = photo.initial || galHotel.initial;
  galImgInner.style.color        = 'rgba(201,169,110,0.18)';
  galImgInner.style.fontSize     = '72px';
  galImgInner.style.fontFamily   = "'Cormorant Garamond',serif";
  galImgInner.style.letterSpacing = '6px';
  galImgLabel.textContent        = photo.label;
  galImgInner.style.opacity      = '0';
  requestAnimationFrame(() => { galImgInner.style.transition = 'opacity 0.3s'; galImgInner.style.opacity = '1'; });
}

function renderThumbs(photos) {
  galleryThumbs.innerHTML = '';
  photos.forEach((p, i) => {
    const t = document.createElement('div');
    t.className = 'gallery-thumb' + (i === galIndex ? ' active' : '');
    t.style.cssText = `background:${p.gradient};font-size:10px;color:rgba(201,169,110,0.4);letter-spacing:1px;text-transform:uppercase;`;
    t.textContent   = p.label.slice(0, 2);
    t.title         = p.label;
    t.addEventListener('click', () => { galIndex = i; renderGallery(); });
    galleryThumbs.appendChild(t);
  });
}

galPrev.addEventListener('click', () => { const p = galHotel.photos[galTab]; galIndex = (galIndex - 1 + p.length) % p.length; renderGallery(); });
galNext.addEventListener('click', () => { const p = galHotel.photos[galTab]; galIndex = (galIndex + 1) % p.length;           renderGallery(); });

document.addEventListener('keydown', e => {
  if (!galleryModal.classList.contains('open')) return;
  if (e.key === 'ArrowRight') galNext.click();
  if (e.key === 'ArrowLeft')  galPrev.click();
  if (e.key === 'Escape')     closeGallery();
});
document.querySelectorAll('.gtab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.gtab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    galTab = btn.dataset.tab; galIndex = 0; renderGallery();
  });
});
galleryClose.addEventListener('click', closeGallery);
galleryBackdrop.addEventListener('click', closeGallery);
function closeGallery() { galleryModal.classList.remove('open'); document.body.style.overflow = ''; }

/* ══════════ BOOKING MODAL ══════════ */
const bookingModal    = document.getElementById('bookingModal');
const bookingBackdrop = document.getElementById('bookingBackdrop');
let   currentBookingHotel = null;

function openBookingModal(hotel) {
  const curUser = JSON.parse(localStorage.getItem('aurum-user') || 'null');
  if (!curUser) { showSideSigninTip(document.body, hotel); return; }
  currentBookingHotel = hotel;
  document.getElementById('modalHotelName').textContent = hotel.name;
  document.getElementById('modalHotelLoc').textContent  = `${hotel.city}, ${hotel.country}`;
  document.getElementById('summaryRate').textContent    = `$${hotel.price}/night`;

  const today    = new Date();
  const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
  const nextWeek = new Date(today); nextWeek.setDate(today.getDate() + 7);
  const toISO    = d => d.toISOString().split('T')[0];
  document.getElementById('bookingCheckin').value  = toISO(tomorrow);
  document.getElementById('bookingCheckout').value = toISO(nextWeek);

  updateSummary(hotel.price);
  document.getElementById('paymentSection')?.classList.add('hidden');
  bookingModal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function showSideSigninTip(button, hotel, msg) {
  document.getElementById('signinTip')?.remove();
  const tip = document.createElement('div');
  tip.id = 'signinTip'; tip.className = 'signin-tip signin-tip--alert';
  tip.innerHTML = `<div class="signin-tip-body"><div class="signin-tip-msg">${msg || 'Please sign in to continue'}</div></div>`;
  document.body.appendChild(tip);

  const rect = button.getBoundingClientRect ? button.getBoundingClientRect() : { right: window.innerWidth / 2, left: window.innerWidth / 2, top: window.innerHeight / 2, height: 0 };
  const pref = 20;
  tip.style.cssText = 'position:fixed;z-index:9999;';
  const tipW = 220, tipH = 60;
  const spR  = window.innerWidth - rect.right;
  const left = spR > tipW + pref ? rect.right + pref : Math.max(12, rect.left - tipW - pref);
  const top  = Math.min(window.innerHeight - tipH - 12, Math.max(12, rect.top + (rect.height - tipH) / 2));
  tip.style.left = `${Math.round(left)}px`; tip.style.top = `${Math.round(top)}px`;
  requestAnimationFrame(() => tip.classList.add('show'));
  tip.addEventListener('click', () => { window.location.href = hotel ? `auth.html?nextBooking=${hotel.id}` : 'auth.html'; });
  const timer = setTimeout(() => { tip.classList.remove('show'); setTimeout(() => tip.remove(), 220); }, 2200);
  tip.addEventListener('mouseenter', () => clearTimeout(timer));
}

function updateSummary(rate) {
  const cin   = new Date(document.getElementById('bookingCheckin').value);
  const cout  = new Date(document.getElementById('bookingCheckout').value);
  const rooms = parseInt(document.getElementById('bookingRooms').value) || 1;
  if (cin && cout && cout > cin) {
    const nights = Math.round((cout - cin) / 86400000);
    document.getElementById('summaryNights').textContent = nights;
    document.getElementById('summaryTotal').textContent  = '$' + (nights * rate * rooms).toLocaleString();
  }
}

document.getElementById('bookingClose').addEventListener('click', closeBooking);
bookingBackdrop.addEventListener('click', closeBooking);
function closeBooking() { bookingModal.classList.remove('open'); document.body.style.overflow = ''; currentBookingHotel = null; }

['bookingCheckin', 'bookingCheckout', 'bookingRooms'].forEach(id => {
  document.getElementById(id)?.addEventListener('change', () => {
    const r = parseFloat((document.getElementById('summaryRate').textContent || '0').replace(/[^0-9.]/g, '')) || 0;
    updateSummary(r);
  });
});

/* Confirm Reservation → show payment section */
document.getElementById('confirmBooking').addEventListener('click', () => {
  const cin  = document.getElementById('bookingCheckin').value;
  const cout = document.getElementById('bookingCheckout').value;
  if (!cin || !cout)    { showToast('Please select dates.', 'error'); return; }
  if (cout <= cin)      { showToast('Check-out must be after check-in.', 'error'); return; }
  const paySection = document.getElementById('paymentSection');
  if (paySection?.classList.contains('hidden')) {
    paySection.classList.remove('hidden');
    setTimeout(() => document.getElementById('payName')?.focus(), 120);
  }
});

/* Pay & Confirm → real API booking */
const payConfirm = document.getElementById('payConfirmBtn');
if (payConfirm) {
  payConfirm.addEventListener('click', async () => {
    const name   = document.getElementById('payName')?.value.trim()   || '';
    const number = (document.getElementById('payNumber')?.value || '').replace(/\s+/g, '');
    const exp    = document.getElementById('payExp')?.value.trim()    || '';
    const cvc    = document.getElementById('payCvc')?.value.trim()    || '';
    if (!name || !number || !exp || !cvc) { showToast('Please complete all payment fields.', 'error'); return; }

    payConfirm.disabled = true; payConfirm.textContent = 'Processing…';

    const token = localStorage.getItem('aurum-token');
    const hotel = currentBookingHotel;
    if (!hotel || !token) {
      payConfirm.disabled = false; payConfirm.textContent = 'Pay & Confirm';
      showToast('Session expired — please sign in again.', 'error'); return;
    }

    try {
      const cin   = document.getElementById('bookingCheckin').value;
      const cout  = document.getElementById('bookingCheckout').value;
      const rooms = parseInt(document.getElementById('bookingRooms').value) || 1;
      const nights = Math.round((new Date(cout) - new Date(cin)) / 86400000);

      const res  = await fetch(`${API_BASE}/bookings`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
        body:    JSON.stringify({
          hotel_id:    hotel.id,
          check_in:    cin,
          check_out:   cout,
          rooms:       rooms,
          guests:      2,
          total_price: hotel.price * nights * rooms,
        }),
      });
      const data = await res.json();

      payConfirm.disabled = false; payConfirm.textContent = 'Pay & Confirm';

      if (data.success) {
        closeBooking();
        showToast(`✔ Reservation confirmed! Booking #${data.data.booking_id}`, 'success');
      } else {
        showToast(data.message || 'Booking failed. Please try again.', 'error');
      }
    } catch (err) {
      payConfirm.disabled = false; payConfirm.textContent = 'Pay & Confirm';
      showToast('Connection error. Please try again.', 'error');
      console.error('Booking error:', err);
    }
  });
}

/* ══════════ AI CONCIERGE ══════════ */
const aiModal    = document.getElementById('aiModal');
const aiMessages = document.getElementById('aiMessages');
const aiInput    = document.getElementById('aiInput');

document.getElementById('openAiChat').addEventListener('click', () => {
  aiModal.classList.add('open'); document.body.style.overflow = 'hidden'; aiInput.focus();
});
document.getElementById('aiBackdrop').addEventListener('click', () => { aiModal.classList.remove('open'); document.body.style.overflow = ''; });
document.getElementById('aiClose').addEventListener('click',    () => { aiModal.classList.remove('open'); document.body.style.overflow = ''; });
document.getElementById('aiSend').addEventListener('click', sendAI);
aiInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendAI(); });

function appendMsg(text, role) {
  const div = document.createElement('div');
  div.className  = `ai-msg ai-msg--${role}`;
  div.innerHTML  = `<div class="ai-msg-bubble">${text}</div>`;
  aiMessages.appendChild(div);
  aiMessages.scrollTop = aiMessages.scrollHeight;
  return div;
}

async function sendAI() {
  const text = aiInput.value.trim();
  if (!text) return;
  appendMsg(text, 'user');
  aiInput.value = '';
  const typing = appendMsg('', 'bot');
  typing.classList.add('ai-typing');

  try {
    const res  = await fetch(`${API_BASE}/ai/concierge`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ message: text }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    typing.classList.remove('ai-typing');

    if (data.success) {
      typing.querySelector('.ai-msg-bubble').innerHTML = data.data.response;

      if (Array.isArray(data.data.suggestions) && data.data.suggestions.length) {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;';
        data.data.suggestions.slice(0, 3).forEach(hotel => {
          const btn = document.createElement('button');
          btn.className = 'btn-outline';
          btn.style.cssText = 'font-size:9px;padding:6px 12px;';
          btn.textContent = `🏨 ${hotel.name} ($${hotel.price})`;
          btn.onclick = () => {
            document.getElementById('s-location').value = hotel.city;
            document.getElementById('s-rooms').value    = 1;
            document.getElementById('s-children').value = 0;
            document.getElementById('s-price').value    = 'any';
            renderResults(filterHotels(hotel.city, 1, 0, 'any'), hotel.city, 1, 0, 'any');
            aiModal.classList.remove('open');
            document.body.style.overflow = '';
            showPage('results');
          };
          wrap.appendChild(btn);
        });
        typing.querySelector('.ai-msg-bubble').appendChild(wrap);
      }
    } else {
      typing.querySelector('.ai-msg-bubble').textContent = data.message || 'AI service unavailable.';
    }
  } catch (err) {
    typing.classList.remove('ai-typing');
    typing.querySelector('.ai-msg-bubble').textContent = 'Connection error. Could not reach AI concierge.';
    console.error('AI error:', err);
  }
  aiMessages.scrollTop = aiMessages.scrollHeight;
}

/* ══════════ TOAST ══════════ */
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(window._tt);
  window._tt = setTimeout(() => t.classList.remove('show'), 4000);
}

/* ══════════ SCROLL ANIMATION ══════════ */
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.style.animation = 'fadeUp 0.6s ease forwards'; obs.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.featured-card, .why-feat').forEach(el => { el.style.opacity = '0'; obs.observe(el); });

/* ══════════ INIT ══════════ */
window.addEventListener('DOMContentLoaded', () => {
  loadHotelsFromAPI();

  // Handle redirect after login with pending booking
  try {
    const params      = new URLSearchParams(window.location.search);
    const openBooking = params.get('openBooking');
    if (openBooking) {
      const hid = parseInt(openBooking, 10);
      setTimeout(() => {
        const h = hotelDatabase.find(x => x.id === hid);
        if (h) openBookingModal(h);
      }, 600);
      history.replaceState(null, '', window.location.pathname);
    }
  } catch (e) { /* ignore */ }

  // Mobile nav
  document.getElementById('navToggle')?.addEventListener('click', () => {
    document.querySelector('.nav-links')?.classList.toggle('mobile-open');
  });
});

/* ══════════ OWNER DASHBOARD LINK ══════════ */
(function () {
  const u = JSON.parse(localStorage.getItem('aurum-user') || 'null');
  if (u?.role === 'owner') {
    const loggedDiv = document.getElementById('navUserLogged');
    if (loggedDiv && !loggedDiv.querySelector('.nav-btn-dash')) {
      const link = document.createElement('a');
      link.href = 'owner-dashboard.html'; link.className = 'nav-btn nav-btn-dash'; link.style.marginRight = '8px'; link.textContent = 'Dashboard';
      loggedDiv.insertBefore(link, loggedDiv.firstChild);
    }
  }
})();
