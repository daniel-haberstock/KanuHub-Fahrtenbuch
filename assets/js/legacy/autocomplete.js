/**
 * Fahrtenbuch – Custom Autocomplete (ersetzt <datalist> für Chromium Kiosk)
 * Multi-Wort-Suche, scrollbar, Touch-freundlich
 */

function _kcsAcFindExact(source, text) {
    if (!text || !window.kcsAcData || !window.kcsAcData[source]) return null;
    return window.kcsAcData[source].find(function(item) {
        return item.text === text;
    }) || null;
}

function _kcsAcInit(input) {
    if (input._kcsAcReady) return;
    input._kcsAcReady = true;

    var $input = $(input);
    var source = $input.attr('data-ac-source');
    if (!source) return;

    var $wrapper = $('<div class="kcs-autocomplete"></div>');
    $input.before($wrapper);
    $wrapper.append($input);

    var $dropdown = $('<div class="kcs-autocomplete-dropdown"></div>');
    $wrapper.append($dropdown);

    var activeIndex = -1;

    function showResults(query) {
        var data = (window.kcsAcData && window.kcsAcData[source]) || [];
        var q = (query || '').toLowerCase().trim();

        var filtered;
        if (q.length === 0) {
            filtered = data;
        } else {
            var words = q.split(/\s+/).filter(function(w) { return w.length > 0; });
            filtered = data.filter(function(item) {
                return words.every(function(word) {
                    return item.search.indexOf(word) !== -1;
                });
            });
        }

        if (filtered.length === 0) {
            $dropdown.removeClass('show').empty();
            return;
        }

        var html = '';
        filtered.forEach(function(item, idx) {
            html += '<div class="kcs-autocomplete-item" data-index="' + idx + '">' +
                    _kcsAcHighlight(item.text, q) + '</div>';
        });
        $dropdown.html(html).addClass('show');
        activeIndex = -1;

        $dropdown.find('.kcs-autocomplete-item').on('click touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var idx = parseInt($(this).attr('data-index'));
            var selected = filtered[idx];
            if (selected) {
                $input.val(selected.text);
                $dropdown.removeClass('show').empty();
                $input.trigger('kcs:select', [selected]);
                $input.trigger('input');
            }
        });
    }

    function _kcsAcHighlight(text, query) {
        if (!query) return text;
        var words = query.split(/\s+/).filter(function(w) { return w.length > 0; });
        var result = text;
        words.forEach(function(word) {
            var regex = new RegExp('(' + word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            result = result.replace(regex, '<strong>$1</strong>');
        });
        return result;
    }

    $input.on('focus', function() { showResults($input.val()); });
    $input.on('input', function() { showResults($input.val()); });

    $input.on('keydown', function(e) {
        var items = $dropdown.find('.kcs-autocomplete-item');
        if (!items.length || !$dropdown.hasClass('show')) return;
        if (e.keyCode === 40) {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, items.length - 1);
            items.removeClass('active');
            $(items[activeIndex]).addClass('active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.keyCode === 38) {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            items.removeClass('active');
            $(items[activeIndex]).addClass('active');
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.keyCode === 13) {
            if (activeIndex >= 0 && activeIndex < items.length) {
                e.preventDefault();
                $(items[activeIndex]).trigger('click');
            }
        } else if (e.keyCode === 27) {
            $dropdown.removeClass('show').empty();
        }
    });

    $(document).on('mousedown touchstart', function(e) {
        if (!$(e.target).closest($wrapper).length) {
            $dropdown.removeClass('show').empty();
        }
    });
}

// Autocomplete für vorhandene und dynamisch erstellte Felder
$(document).ready(function() {
    $('.kcs-ac-input').each(function() { _kcsAcInit(this); });

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) {
                    if ($(node).hasClass('kcs-ac-input')) _kcsAcInit(node);
                    $(node).find('.kcs-ac-input').each(function() { _kcsAcInit(this); });
                }
            });
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
});
