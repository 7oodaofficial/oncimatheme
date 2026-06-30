<div class="ow-search" @keydown.escape="searchOpen = false" @click.away="searchOpen = false">
    <form role="search" method="get" class="ow-search-bar" action="<?php echo esc_url(home_url('/')); ?>">
        <span class="ow-search-bar__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input
            type="search"
            placeholder="<?php _e('ابحث عن فيلم أو مسلسل...', 'onwatch'); ?>"
            value="<?php echo get_search_query(); ?>"
            name="s"
            x-ref="searchInput"
            x-model="searchQuery"
            @input.debounce.350ms="liveSearch"
        >
        <button type="submit" aria-label="<?php _e('بحث', 'onwatch'); ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
    </form>
    <div class="ow-search__results" x-show="searchResults.length > 0 && searchQuery.length > 1" x-cloak>
        <template x-for="r in searchResults" :key="r.id">
            <a :href="r.url" class="ow-search__item">
                <img :src="r.poster_url || ''" :alt="r.title" loading="lazy" class="ow-search__item-img">
                <div class="ow-search__item-info">
                    <strong x-text="r.title"></strong>
                    <span x-text="r.year + ' - ' + (r.type === 'movies' ? '<?php _e('فيلم', 'onwatch'); ?>' : '<?php _e('مسلسل', 'onwatch'); ?>')"></span>
                </div>
            </a>
        </template>
        <a :href="'<?php echo esc_url(home_url('/')); ?>?s=' + encodeURIComponent(searchQuery)" class="ow-search__all"><?php _e('عرض الكل', 'onwatch'); ?> ←</a>
    </div>
</div>
