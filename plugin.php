<?php
/*
Plugin Name: Bulk Links
Plugin URI: https://github.com/dominggo/yourls-bulk-links
Description: Tick multiple links on the main list and delete them all at once, and a Bulk Add Links page to create many short links from a pasted list. Pure admin-UI additions via YOURLS' own plugin hooks -- no settings, no external service.
Version: 1.0.0
Author: dominggo
Author URI: https://github.com/dominggo
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'BULKLINKS_PAGE_SLUG', 'bulk-links' );

/* -------------------------------------------------------------------------
 * Checkbox column on the main links list ("/admin/index.php")
 * ---------------------------------------------------------------------- */

yourls_add_filter( 'table_head_cells', 'bulklinks_head_cell' );
function bulklinks_head_cell( $cells ) {
    return array( 'bulk' => '<input type="checkbox" id="bulklinks_select_all" title="Select all">' ) + $cells;
}

yourls_add_filter( 'table_add_row_cell_array', 'bulklinks_row_cell' );
function bulklinks_row_cell( $cells, $keyword ) {
    return array(
        'bulk' => array(
            'template' => '<input type="checkbox" class="bulklinks_cb" value="%kw%">',
            'kw'       => $keyword,
        ),
    ) + $cells;
}

// Toolbar (select count + Delete button) just above the table
yourls_add_filter( 'table_head_start', 'bulklinks_toolbar' );
function bulklinks_toolbar( $start ) {
    $nonce = yourls_create_nonce( 'bulklinks_delete' );
    $toolbar = '<div id="bulklinks_toolbar" style="margin:10px 0">' .
        '<input type="hidden" id="bulklinks_nonce" value="' . $nonce . '">' .
        '<button type="button" id="bulklinks_delete_btn" class="button" disabled>' .
        'Delete selected (<span id="bulklinks_count">0</span>)</button></div>';
    return $toolbar . $start;
}

// JS: only on the main list page (html_head fires on every admin page with a $context)
yourls_add_action( 'html_head', 'bulklinks_js' );
function bulklinks_js( $context ) {
    if( $context !== 'index' ) {
        return;
    }
    ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function checked() {
        return Array.prototype.slice.call(document.querySelectorAll('.bulklinks_cb:checked')).map(function(cb){ return cb.value; });
    }
    function refresh() {
        var n = checked().length;
        document.getElementById('bulklinks_count').textContent = n;
        document.getElementById('bulklinks_delete_btn').disabled = (n === 0);
    }
    document.addEventListener('change', function(e) {
        if( e.target.classList && e.target.classList.contains('bulklinks_cb') ) {
            refresh();
        }
        if( e.target.id === 'bulklinks_select_all' ) {
            Array.prototype.forEach.call(document.querySelectorAll('.bulklinks_cb'), function(cb){ cb.checked = e.target.checked; });
            refresh();
        }
    });
    var btn = document.getElementById('bulklinks_delete_btn');
    if( btn ) {
        btn.addEventListener('click', function() {
            var kws = checked();
            if( kws.length === 0 ) { return; }
            if( !confirm('Delete ' + kws.length + ' link(s)? This cannot be undone.') ) { return; }
            var fd = new FormData();
            fd.append('action', 'bulklinks_delete');
            fd.append('nonce', document.getElementById('bulklinks_nonce').value);
            kws.forEach(function(k){ fd.append('keywords[]', k); });
            fetch('admin-ajax.php', { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    var msg = 'Deleted ' + data.deleted + ' link(s).';
                    if( data.failed && data.failed.length ) { msg += ' Already gone: ' + data.failed.join(', '); }
                    alert(msg);
                    location.reload();
                })
                .catch(function(){ alert('Request failed -- nothing was deleted.'); });
        });
    }
});
</script>
    <?php
}

/* -------------------------------------------------------------------------
 * Bulk delete AJAX action -- admin-ajax.php dispatches unknown actions to
 * yourls_do_action('yourls_ajax_<action>'), already behind yourls_maybe_require_auth()
 * ---------------------------------------------------------------------- */

yourls_add_action( 'yourls_ajax_bulklinks_delete', 'bulklinks_ajax_delete' );
function bulklinks_ajax_delete() {
    yourls_verify_nonce( 'bulklinks_delete' );

    $keywords = isset( $_REQUEST['keywords'] ) ? (array) $_REQUEST['keywords'] : array();
    $deleted  = 0;
    $failed   = array();

    foreach( $keywords as $kw ) {
        $kw = yourls_sanitize_keyword( $kw );
        if( $kw === '' ) {
            continue;
        }
        if( yourls_keyword_is_taken( $kw ) ) {
            yourls_delete_link_by_keyword( $kw );
            $deleted++;
        } else {
            $failed[] = $kw;
        }
    }

    echo json_encode( array( 'deleted' => $deleted, 'failed' => $failed ) );
    die();
}

/* -------------------------------------------------------------------------
 * Bulk Add Links admin page
 * ---------------------------------------------------------------------- */

yourls_add_action( 'plugins_loaded', 'bulklinks_register_page' );
function bulklinks_register_page() {
    yourls_register_plugin_page( BULKLINKS_PAGE_SLUG, 'Bulk Add Links', 'bulklinks_render_page' );
}

function bulklinks_is_valid_url( $url ) {
    return filter_var( $url, FILTER_VALIDATE_URL ) && preg_match( '#^https?://#i', $url );
}

function bulklinks_render_page() {
    $results = array();

    if( isset( $_POST['bulklinks_submit'] ) ) {
        yourls_verify_nonce( 'bulklinks_add' );
        $raw   = isset( $_POST['bulklinks_input'] ) ? $_POST['bulklinks_input'] : '';
        $lines = preg_split( '/\r\n|\r|\n/', trim( $raw ) );

        foreach( $lines as $line ) {
            $line = trim( $line );
            if( $line === '' ) {
                continue;
            }
            $parts   = preg_split( '/\s+/', $line, 2 );
            $url     = $parts[0];
            $keyword = isset( $parts[1] ) ? trim( $parts[1] ) : '';

            if( !bulklinks_is_valid_url( $url ) ) {
                $results[] = array( 'url' => $url, 'ok' => false, 'message' => 'Not a valid http(s) URL' );
                continue;
            }
            $r  = yourls_add_new_link( $url, $keyword );
            $ok = !empty( $r['status'] ) && $r['status'] === 'success';
            $results[] = array(
                'url'     => $url,
                'ok'      => $ok,
                'message' => $ok ? $r['shorturl'] : ( isset( $r['message'] ) ? $r['message'] : 'unknown error' ),
            );
        }
    }

    $nonce = yourls_create_nonce( 'bulklinks_add' );

    echo <<<HTML
        <h2>Bulk Add Links</h2>
        <p>One URL per line. Add a keyword after a space for a custom one (optional) &mdash;
        otherwise YOURLS generates one: <code>https://example.com mykeyword</code></p>
        <form method="post">
        <input type="hidden" name="nonce" value="$nonce" />
        <textarea name="bulklinks_input" rows="10" cols="70"
            placeholder="https://example.com
https://example.org mykeyword"></textarea><br>
        <p><input type="submit" name="bulklinks_submit" value="Create links" /></p>
        </form>
HTML;

    if( $results ) {
        echo '<h3>Results</h3><ul>';
        foreach( $results as $r ) {
            $color = $r['ok'] ? 'green' : 'red';
            echo '<li style="color:' . $color . '">' . htmlspecialchars( $r['url'] ) . ' &mdash; ' . htmlspecialchars( $r['message'] ) . '</li>';
        }
        echo '</ul>';
    }
}
