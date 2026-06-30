document.addEventListener('alpine:init', () => {
  Alpine.data('onwatch', () => ({
    menuOpen: false,
    searchOpen: false,
    searchQuery: '',
    searchResults: [],
    authModal: false,
    trailerModal: false,
    trailerUrl: '',
    downloadModal: false,
    downloadLinksHtml: '',
    reportModal: false,
    playerLoading: true,
    activeServer: 0,
    activeSeason: null,
    showAllSeasons: false,
    seasonContent: '',
    seasonCache: {},
    activeGenre: null,
    genreContent: '',
    continueWatching: [],
    continueHtml: '',
    hasMore: true,
    loadingMore: false,
    currentPage: 1,
    queryVars: {},
    headerSolid: false,

    loginData: { username: '', password: '' },
    registerData: { username: '', email: '', password: '' },
    loginError: '',
    registerError: '',
    reportData: { post_id: 0, type: '', message: '' },
    reportMessage: '',

    init() {
      this.loadFavorites();
      this.loadHistory();
      this.renderContinueWatching();
      this.initScrollHeader();
      this.initSubMenus();
      this.initFirstGenre();
    },

    initFirstGenre() {
      this.$nextTick(() => {
        var section = document.querySelector('.ow-genre-section');
        if (section && section.dataset.firstGenre && !this.genreContent) {
          this.loadGenre(section.dataset.firstGenre);
        }
      });
    },

    initSubMenus() {
      document.querySelectorAll('.ow-nav .menu-item-has-children > a').forEach(link => {
        link.addEventListener('click', e => {
          if (window.innerWidth > 991) return;
          e.preventDefault();
          const li = link.parentElement;
          li.classList.toggle('is-open');
        });
      });
    },

    initScrollHeader() {
      if (window.scrollY > 50) this.headerSolid = true;
      window.addEventListener('scroll', () => {
        this.headerSolid = window.scrollY > 50;
      }, { passive: true });
    },

    openAuth(tab = 'login') {
      this.authModal = tab;
      this.loginError = '';
      this.registerError = '';
    },

    closeAll() {
      this.menuOpen = false;
      this.searchOpen = false;
      this.trailerModal = false;
      this.downloadModal = false;
      this.reportModal = false;
    },

    openTrailer(url) {
      this.trailerUrl = url;
      this.playerLoading = true;
      this.trailerModal = true;
    },

    openDownload(postId) {
      this.downloadModal = true;
      this.downloadLinksHtml = '<p class="ow-text-muted">' + onwatchVars.strings.noResults + '</p>';
    },

    openReport(postId) {
      this.reportData = { post_id: postId, type: '', message: '' };
      this.reportMessage = '';
      this.reportModal = true;
    },

    submitLogin() {
      this.loginError = '';
      const formData = new FormData();
      formData.append('action', 'action_login');
      formData.append('_wplogin', onwatchVars.nonce);
      formData.append('username', this.loginData.username);
      formData.append('password', this.loginData.password);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.status === 200) {
            location.reload();
          } else {
            this.loginError = d.error || 'Login failed';
          }
        })
        .catch(() => { this.loginError = 'Login failed'; });
    },

    submitRegister() {
      this.registerError = '';
      const formData = new FormData();
      formData.append('action', 'action_signup');
      formData.append('_wpsignup', onwatchVars.nonce);
      formData.append('username', this.registerData.username);
      formData.append('email', this.registerData.email);
      formData.append('password', this.registerData.password);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.status === 200) {
            location.reload();
          } else {
            this.registerError = d.error || 'Registration failed';
          }
        })
        .catch(() => { this.registerError = 'Registration failed'; });
    },

    submitReport() {
      const formData = new FormData();
      formData.append('action', 'onwatch_submit_report');
      formData.append('nonce', onwatchVars.nonce);
      formData.append('post_id', this.reportData.post_id);
      formData.append('type', this.reportData.type);
      formData.append('message', this.reportData.message);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            this.reportModal = false;
            this.toast(d.data.message, 'success');
          } else {
            this.reportMessage = d.data.message || 'Error';
          }
        });
    },

    submitRating(postId, score) {
      if (!this.isLoggedIn()) {
        this.openAuth('login');
        return;
      }
      const formData = new FormData();
      formData.append('action', 'onwatch_submit_rating');
      formData.append('nonce', onwatchVars.nonce);
      formData.append('post_id', postId);
      formData.append('score', score);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            this.toast(d.data.message, 'success');
          } else {
            this.toast(d.data.message, 'error');
          }
        });
    },

    liveSearch() {
      if (this.searchQuery.length < 2) {
        this.searchResults = [];
        return;
      }
      const formData = new FormData();
      formData.append('action', 'onwatch_live_search');
      formData.append('nonce', onwatchVars.nonce);
      formData.append('s', this.searchQuery);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) this.searchResults = d.data.results;
        });
    },

    loadGenre(slug) {
      const formData = new FormData();
      formData.append('action', 'onwatch_genre_tab');
      formData.append('nonce', onwatchVars.nonce);
      formData.append('genre_slug', slug);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) this.genreContent = d.data.html;
        });
    },

    switchSeason(seasonId, seriesId) {
      if (this.seasonCache[seasonId]) {
        this.seasonContent = this.seasonCache[seasonId];
        this.activeSeason = seasonId;
        return;
      }
      const formData = new FormData();
      formData.append('action', 'onwatch_get_season');
      formData.append('nonce', onwatchVars.nonce);
      formData.append('series_id', seriesId);
      formData.append('season_number', seasonId);

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            this.seasonCache[seasonId] = d.data.html;
            this.seasonContent = d.data.html;
            this.activeSeason = seasonId;
          }
        });
    },

    loadMore() {
      if (this.loadingMore || !this.hasMore) return;
      this.loadingMore = true;
      this.currentPage++;

      const formData = new FormData();
      formData.append('action', 'onwatch_load_more');
      formData.append('nonce', onwatchVars.nonce);
      formData.append('page', this.currentPage);
      formData.append('query_vars', JSON.stringify(this.queryVars));

      fetch(onwatchVars.ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            const grid = document.querySelector('[data-archive-grid], .ow-grid');
            if (grid) grid.insertAdjacentHTML('beforeend', d.data.html);
            this.hasMore = d.data.has_more;
          }
          this.loadingMore = false;
        })
        .catch(() => { this.loadingMore = false; });
    },

    toggleFav(id, title, url, poster) {
      let favs = JSON.parse(localStorage.getItem('onwatch_favorites') || '[]');
      const idx = favs.findIndex(f => f.id === id);
      if (idx > -1) {
        favs.splice(idx, 1);
        this.toast(onwatchVars.strings.removedFav, 'info');
      } else {
        if (favs.length >= 100) favs.pop();
        favs.unshift({ id, title, type: 'movie', url, poster });
        this.toast(onwatchVars.strings.addedFav, 'success');
      }
      localStorage.setItem('onwatch_favorites', JSON.stringify(favs));
      this.updateFavIcons();
    },

    loadFavorites() {
      this.favorites = JSON.parse(localStorage.getItem('onwatch_favorites') || '[]');
    },

    updateFavIcons() {
      this.loadFavorites();
    },

    updateHistory(postId) {
      if (!postId) return;
      let history = JSON.parse(localStorage.getItem('onwatch_history') || '[]');
      const titleEl = document.querySelector('.ow-details-hero__title');
      const title = titleEl ? titleEl.textContent : '';
      const posterEl = document.querySelector('.ow-details-hero__poster img') || document.querySelector('.ow-watch-hero-poster img');
      let poster = posterEl ? posterEl.src : '';
      if (poster && (poster.includes('placeholder.svg') || poster.includes('data:image'))) poster = '';
      const idx = history.findIndex(h => h.id === postId);
      if (idx > -1) {
        history[idx].progress = 100;
        if (poster) history[idx].poster = poster;
        history[idx].timestamp = Date.now();
      } else {
        history.unshift({ id: postId, title, type: 'movie', url: window.location.href, poster, progress: 0, timestamp: Date.now() });
        if (history.length > 30) history.pop();
      }
      localStorage.setItem('onwatch_history', JSON.stringify(history));
      this.renderContinueWatching();
    },

    loadHistory() {
      this.history = JSON.parse(localStorage.getItem('onwatch_history') || '[]');
    },

    clearHistory() {
      localStorage.removeItem('onwatch_history');
      this.continueWatching = [];
      this.continueHtml = '';
    },

    renderContinueWatching() {
      this.loadHistory();
      this.continueWatching = this.history.filter(h => h.progress < 100).slice(0, 10);
      if (this.continueWatching.length === 0) {
        this.continueHtml = '';
        return;
      }
      var ph = onwatchVars.url + '/resources/assets/img/placeholder.svg';
      var html = '';
      this.continueWatching.forEach(h => {
        html += '<article class="ow-card"><a href="' + h.url + '" class="ow-card__link">';
        html += '<div class="ow-card__image">';
        if (h.poster) html += '<img src="' + h.poster + '" loading="lazy" width="342" height="513" onerror="this.onerror=null;var p=\'' + ph + '\';if(this.src!==p)this.src=p">';
        else html += '<div class="ow-card__placeholder"></div>';
        html += '<div class="ow-card__progress"><span class="ow-card__progress-bar" style="width:' + h.progress + '%"></span></div>';
        html += '</div><h3 class="ow-card__title">' + h.title + '</h3></a></article>';
      });
      this.continueHtml = html;
    },

    isWatched(episodeId) {
      return localStorage.getItem('onwatch_watched_' + episodeId) === 'true';
    },

    markWatched(episodeId) {
      localStorage.setItem('onwatch_watched_' + episodeId, 'true');
    },

    share(title, url) {
      if (navigator.share) {
        navigator.share({ title, url }).catch(() => {});
      } else {
        const popup = document.createElement('div');
        popup.className = 'ow-share-popup';
        popup.innerHTML = '<div class="ow-share-popup__inner">' +
          '<a href="https://wa.me/?text=' + encodeURIComponent(title + ' ' + url) + '" target="_blank" class="ow-btn ow-btn--secondary">WhatsApp</a>' +
          '<a href="https://t.me/share/url?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title) + '" target="_blank" class="ow-btn ow-btn--secondary">Telegram</a>' +
          '<a href="https://twitter.com/intent/tweet?text=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(url) + '" target="_blank" class="ow-btn ow-btn--secondary">X</a>' +
          '<button onclick="this.parentElement.parentElement.remove(); onwatchToast(\'' + onwatchVars.strings.copied + '\', \'success\'); navigator.clipboard.writeText(\'' + url + '\')" class="ow-btn ow-btn--secondary">' + onwatchVars.strings.copied + '</button>' +
          '</div>';
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 5000);
      }
    },

    toast(message, type = 'info') {
      let c = document.getElementById('ow-toasts');
      if (!c) { c = document.createElement('div'); c.id = 'ow-toasts'; c.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:2000;display:flex;flex-direction:column;gap:0.5rem'; document.body.appendChild(c); }
      const t = document.createElement('div');
      t.textContent = message;
      const colors = { success: '#22c55e', error: '#ef4444', info: '#252535' };
      t.style.cssText = 'padding:0.75rem 1.125rem;border-radius:8px;font-size:0.8125rem;font-weight:600;color:#fff;background:' + (colors[type] || colors.info) + ';box-shadow:0 8px 24px rgba(0,0,0,0.3);animation:ow-toast-in 0.25s ease;max-width:340px';
      c.appendChild(t);
      setTimeout(() => { t.style.cssText += ';opacity:0;transform:translateY(8px);transition:all 0.2s ease'; setTimeout(() => t.remove(), 250); }, 2500);
    },

    isLoggedIn() {
      return document.body.classList.contains('logged-in');
    },

    dragStart(e) {
      const el = e.currentTarget;
      el.isDragging = true;
      el.startX = e.pageX - el.offsetLeft;
      el.scrollLeftStart = el.scrollLeft;
    },

    dragMove(e) {
      const el = e.currentTarget;
      if (!el.isDragging) return;
      e.preventDefault();
      const x = e.pageX - el.offsetLeft;
      const walk = (x - el.startX) * 1.5;
      el.scrollLeft = el.scrollLeftStart - walk;
    },

    dragEnd(e) {
      const el = e.currentTarget;
      el.isDragging = false;
    }
  }));
});

function onwatchToast(message, type = 'info') {
  let c = document.getElementById('ow-toasts');
  if (!c) { c = document.createElement('div'); c.id = 'ow-toasts'; c.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:2000;display:flex;flex-direction:column;gap:0.5rem'; document.body.appendChild(c); }
  const t = document.createElement('div');
  t.textContent = message;
  const colors = { success: '#22c55e', error: '#ef4444', info: '#252535' };
  t.style.cssText = 'padding:0.75rem 1.125rem;border-radius:8px;font-size:0.8125rem;font-weight:600;color:#fff;background:' + (colors[type] || colors.info) + ';box-shadow:0 8px 24px rgba(0,0,0,0.3);animation:ow-toast-in 0.25s ease;max-width:340px';
  c.appendChild(t);
  setTimeout(() => { t.style.cssText += ';opacity:0;transform:translateY(8px);transition:all 0.2s ease'; setTimeout(() => t.remove(), 250); }, 2500);
}

document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    document.querySelectorAll('[data-episode-id]').forEach(function(el) {
      var eid = el.dataset.episodeId;
      if (localStorage.getItem('onwatch_watched_' + eid) === 'true') {
        el.classList.add('is-watched');
      }
    });
  }, 5000);

  var placeholderUrl = (typeof onwatchVars !== 'undefined' ? onwatchVars.url : '') + '/resources/assets/img/placeholder.svg';
  document.addEventListener('error', function(e) {
    var target = e.target;
    if (target.tagName !== 'IMG') return;
    if (target.src === placeholderUrl) return;
    var parent = target.closest('.ow-card__image, .ow-details-hero__poster, .ow-episode-card__thumb, .ow-watch-item__thumb, .ow-hero__slide, .ow-watch-hero-poster, .ow-watch-item__thumb');
    if (parent) {
      target.onerror = null;
      target.src = placeholderUrl;
    }
  }, true);
});
