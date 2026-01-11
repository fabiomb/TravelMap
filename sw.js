/**
 * TravelMap Service Worker
 * Caches map tiles for faster loading and offline support
 */

const CACHE_NAME = 'travelmap-tiles-v3';  // Incremented to force cache refresh after CARTO migration
const CACHE_MAX_AGE = 7 * 24 * 60 * 60 * 1000; // 7 days

// Tile domains to cache
const TILE_DOMAINS = [
    'basemaps.cartocdn.com',
    'a.basemaps.cartocdn.com',
    'b.basemaps.cartocdn.com',
    'c.basemaps.cartocdn.com',
    'd.basemaps.cartocdn.com',
    'tiles.openfreemap.org',
    'api.maptiler.com',
    'tile.openstreetmap.org'
];

// File extensions to cache from tile servers
const CACHEABLE_EXTENSIONS = ['.png', '.jpg', '.jpeg', '.webp', '.pbf', '.mvt'];

self.addEventListener('install', (event) => {
    console.log('[SW] Installing TravelMap Service Worker');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('[SW] Activating TravelMap Service Worker');
    event.waitUntil(
        Promise.all([
            clients.claim(),
            // Clean old caches
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => name.startsWith('travelmap-') && name !== CACHE_NAME)
                        .map(name => caches.delete(name))
                );
            })
        ])
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    
    // Check if this is a tile request we should cache
    const isTileRequest = TILE_DOMAINS.some(domain => url.hostname.includes(domain));
    const isCacheableFile = CACHEABLE_EXTENSIONS.some(ext => url.pathname.endsWith(ext));
    
    if (isTileRequest || isCacheableFile) {
        event.respondWith(handleTileRequest(event.request));
    }
});

async function handleTileRequest(request) {
    const cache = await caches.open(CACHE_NAME);
    
    // NETWORK FIRST strategy for tiles - this ensures fresh tiles are loaded
    // Only fallback to cache if network fails
    try {
        const response = await fetch(request);
        
        // Cache successful responses
        if (response.status === 200) {
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        // Network error - try cache
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // No cache either - return transparent fallback for tiles
        return new Response('', {
            status: 408,
            statusText: 'Request Timeout'
        });
    }
}

async function refreshCache(request, cache) {
    // This function is no longer used with network-first strategy
    // Kept for backward compatibility if needed
    try {
        const response = await fetch(request);
        if (response.status === 200) {
            cache.put(request, response.clone());
        }
    } catch (error) {
        // Silently fail - we have cached version
    }
}

// Periodic cache cleanup
self.addEventListener('message', (event) => {
    if (event.data === 'cleanup') {
        cleanupCache();
    } else if (event.data === 'clearCache') {
        // Allow manual cache clearing
        clearAllCaches();
    }
});

async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(
        cacheNames
            .filter(name => name.startsWith('travelmap-'))
            .map(name => caches.delete(name))
    );
    console.log('[SW] All caches cleared');
}

async function cleanupCache() {
    const cache = await caches.open(CACHE_NAME);
    const requests = await cache.keys();
    
    // Limit cache size to ~100MB worth of tiles (rough estimate)
    const MAX_ENTRIES = 2000;
    
    if (requests.length > MAX_ENTRIES) {
        // Delete oldest entries (first in the list)
        const toDelete = requests.slice(0, requests.length - MAX_ENTRIES);
        await Promise.all(toDelete.map(req => cache.delete(req)));
        console.log('[SW] Cleaned up', toDelete.length, 'cached tiles');
    }
}
