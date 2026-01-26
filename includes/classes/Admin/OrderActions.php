<?php
namespace bKash\PGW\Admin;

use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transaction;
use bKash\PGW\Operations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderActions {
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'admin_post_bkash_capture', array( $this, 'handle_capture' ) );
        add_action( 'admin_post_bkash_void', array( $this, 'handle_void' ) );

        // Also render buttons on the new WC Orders single-view page (admin.php?page=wc-orders&action=edit&id=...)
        add_action( 'admin_notices', array( $this, 'maybe_render_orders_list_buttons' ) );
        // Ensure footer JS runs to inject card in various admin editors
        add_action( 'admin_footer', array( $this, 'render_footer_actions_js' ) );
        // AJAX endpoint for client-side click logging
        add_action( 'wp_ajax_bkash_log_click', array( $this, 'ajax_log_click' ) );
        // AJAX capture/void actions (no page reload)
        add_action( 'wp_ajax_bkash_capture_ajax', array( $this, 'ajax_capture' ) );
        add_action( 'wp_ajax_bkash_void_ajax', array( $this, 'ajax_void' ) );

        // Action Scheduler hooks (if used)
        add_action( 'bkash_capture_job', array( $this, 'process_capture_job' ) );
        add_action( 'bkash_void_job', array( $this, 'process_void_job' ) );
        // Fixed-position override removed; UI now injects a postbox under Order Notes.
    }

    /**
     * Output footer JS to insert the bKash actions postbox client-side.
     * This runs on order edit screens to support wc-admin/react and classic editors.
     */
    public function render_footer_actions_js() {
        if ( ! is_admin() || ! current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        global $pagenow;
        $order_id = 0;
        if ( isset( $pagenow ) && $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
            $order_id = absint( $_GET['post'] );
        } elseif ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $order_id = absint( $_GET['id'] );
        }

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            return;
        }

        // Only allow buttons for authorized bKash transactions on on-hold orders
        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction ) {
            return;
        }
        $intent_lc = strtolower( (string) $transaction->getIntent() );
        $status_lc = strtolower( (string) $transaction->getStatus() );
        if ( ! $order->has_status( 'on-hold' ) || ! in_array( $intent_lc, array( 'authorize', 'authorization' ), true ) || $status_lc !== 'authorized' ) {
            return;
        }

        $click_nonce = wp_create_nonce( 'bkash_admin_actions' );
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );

        ?>
        <script>(function(){
            var ajaxUrl = '<?php echo $ajax_url; ?>';
            function showBkashNotice(type, message){
                try{
                    var notice = document.createElement('div');
                    notice.className = 'notice ' + (type === 'success' ? 'notice-success' : 'notice-error') + ' is-dismissible';
                    notice.style.margin = '0 0 12px';
                    notice.innerHTML = '<p>' + message + '</p>';
                    var container = document.querySelector('#wpbody-content') || document.body;
                    container.insertBefore(notice, container.firstChild);
                    setTimeout(function(){ if(notice && notice.parentNode) notice.parentNode.removeChild(notice); }, 6000);
                }catch(e){}
            }
            function injectBkashButtons() {
                // Check if already injected
                if (document.getElementById('bkash-quick-actions')) return;

                // Find the WooCommerce Order Notes "inside" container
                var notesContainer = document.querySelector('#woocommerce-order-notes .inside');
                if (!notesContainer) return;

                // Create Button Container
                var btnWrapper = document.createElement('div');
                btnWrapper.id = 'bkash-quick-actions';
                btnWrapper.style.marginTop = '15px';
                btnWrapper.style.paddingTop = '15px';
                btnWrapper.style.borderTop = '1px solid #eee';
                btnWrapper.style.marginLeft = '12px';

                btnWrapper.innerHTML = `
                    <p style="font-weight:600; margin-bottom: 8px;">bKash Quick Actions</p>
                    <div style="display:flex; gap: 8px;">
                        <button type="button" class="button button-primary bkash-override-btn" data-action="capture" data-order-id="<?php echo $order_id; ?>" data-nonce="<?php echo $click_nonce; ?>">Capture</button>
                        <button type="button" class="button button-secondary bkash-override-btn" data-action="void" data-order-id="<?php echo $order_id; ?>" data-nonce="<?php echo $click_nonce; ?>">Void</button>
                    </div>
                    <div class="bkash-override-result" style="margin-top:8px;"></div>
                `;

                notesContainer.appendChild(btnWrapper);

                // Loader helpers
                function showBkashLoader(){
                    if(document.getElementById('bkash-loader-overlay')) return;
                    var ov = document.createElement('div');
                    ov.id = 'bkash-loader-overlay';
                    ov.style.position = 'fixed';
                    ov.style.top = '0';
                    ov.style.left = '0';
                    ov.style.width = '100%';
                    ov.style.height = '100%';
                    ov.style.background = 'rgba(0,0,0,0.15)';
                    ov.style.zIndex = '99999';
                    ov.style.display = 'flex';
                    ov.style.alignItems = 'center';
                    ov.style.justifyContent = 'center';
                    var spinner = document.createElement('div');
                    spinner.style.padding = '12px 18px';
                    spinner.style.background = '#fff';
                    spinner.style.borderRadius = '6px';
                    spinner.style.boxShadow = '0 1px 4px rgba(0,0,0,0.2)';
                    spinner.innerText = 'Loading...';
                    ov.appendChild(spinner);
                    document.body.appendChild(ov);
                }
                function hideBkashLoader(){
                    var el = document.getElementById('bkash-loader-overlay');
                    if(el && el.parentNode) el.parentNode.removeChild(el);
                }

                // Wire up click handlers for the injected buttons
                var captureBtn = btnWrapper.querySelector('.bkash-override-btn[data-action="capture"]');
                var voidBtn = btnWrapper.querySelector('.bkash-override-btn[data-action="void"]');
                function handleClick(e){
                    e.preventDefault();
                    var btn = e.currentTarget;
                    var action = btn.getAttribute('data-action');
                    var orderId = btn.getAttribute('data-order-id');
                    var nonce = btn.getAttribute('data-nonce');
                    var ajaxAction = action === 'capture' ? 'bkash_capture_ajax' : 'bkash_void_ajax';
                    var body = 'order_id=' + encodeURIComponent(orderId) + '&_ajax_nonce=' + encodeURIComponent(nonce);
                    // disable buttons and show loader
                    var buttons = btnWrapper.querySelectorAll('.bkash-override-btn');
                    buttons.forEach(function(b){ b.disabled = true; b.classList.add('disabled'); b.setAttribute('aria-disabled','true'); });
                    showBkashLoader();
                    fetch( ajaxUrl + '?action=' + ajaxAction, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body
                    } ).then(function(r){ return r.json(); }).then(function(json){
                        var res = btnWrapper.querySelector('.bkash-override-result');
                        if ( json && json.success ){
                            btnWrapper.style.display = 'none';
                            var alert = document.createElement('div');
                            alert.className = 'notice notice-success';
                            alert.style.padding = '6px';
                            alert.style.margin = '0';
                            alert.innerText = 'bKash ' + action + ' succeeded';
                            document.body.insertBefore(alert, document.body.firstChild);
                            showBkashNotice('success', 'bKash ' + action + ' succeeded');
                            // update status display if present
                            var newStatus = action === 'capture' ? 'processing' : 'cancelled';
                            var statusSelect = document.querySelector('#order_status');
                            if(statusSelect){ try{ statusSelect.value = 'wc-' + newStatus; statusSelect.dispatchEvent(new Event('change')); }catch(e){} }
                            var statusText = document.querySelector('.order_status, .woocommerce-order-status, #order_status_text');
                            if(statusText){ try{ statusText.textContent = newStatus; }catch(e){} }
                        } else {
                            // show top-level notice only (remove inline error under buttons)
                            showBkashNotice('error', 'bKash ' + action + ' failed');
                        }
                    }).catch(function(){
                        // network error: show top-level notice only
                        showBkashNotice('error', 'Network error while contacting bKash');
                    }).finally(function(){
                        // re-enable and hide loader (if still present)
                        buttons.forEach(function(b){ b.disabled = false; b.classList.remove('disabled'); b.removeAttribute('aria-disabled'); });
                        hideBkashLoader();
                    });
                }
                if ( captureBtn ) captureBtn.addEventListener('click', handleClick);
                if ( voidBtn ) voidBtn.addEventListener('click', handleClick);
            }

            // Run on load and observe for dynamic rendering (HPOS/React)
            injectBkashButtons();
            var observer = new MutationObserver(injectBkashButtons);
            observer.observe(document.body, { childList: true, subtree: true });
        })();</script>
        <?php
    }

    /**
     * Render a persistent fixed override box with Capture/Void buttons.
     * This forcibly shows the buttons (temporary) on order edit screens.
     */
    public function render_override_box() {
        // Disabled: fixed-position override box removed in favor of the Order Notes postbox.
        return;

        if ( ! is_admin() || ! current_user_can( 'edit_shop_orders' ) ) {
            return;
        }

        global $pagenow;
        $order_id = 0;
        if ( $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
            $order_id = absint( $_GET['post'] );
        } elseif ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-orders' && isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $order_id = absint( $_GET['id'] );
        }

        if ( ! $order_id ) {
            return;
        }

        // Only render override actions for eligible bKash orders:
        // 1) payment method is bKash
        // 2) intent is 'authorize' / 'authorization'
        // 3) order status is 'on-hold'
        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            return;
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction ) {
            return;
        }

        $intent_lc = strtolower( (string) $transaction->getIntent() );
        $status_lc = strtolower( (string) $transaction->getStatus() );
        $enabled = $order->has_status( 'on-hold' ) && in_array( $intent_lc, array( 'authorize', 'authorization' ), true ) && $status_lc === 'authorized';
        if ( ! $enabled ) {
            return;
        }

        $capture_url = wp_nonce_url( admin_url( 'admin-post.php?action=bkash_capture&order_id=' . $order_id ), 'bkash_capture_' . $order_id );
        $void_url    = wp_nonce_url( admin_url( 'admin-post.php?action=bkash_void&order_id=' . $order_id ), 'bkash_void_' . $order_id );
        $click_nonce = wp_create_nonce( 'bkash_admin_actions' );

        // Render AJAX buttons (do not navigate)
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
        ?>
        <div style="position:fixed;right:20px;top:120px;z-index:99999;max-width:320px;background:#fff;border-left:4px solid #00a0d2;box-shadow:0 1px 6px rgba(0,0,0,.12);padding:12px;">
            <p style="margin:0 0 8px;"><strong>bKash Actions (override)</strong></p>
            <p style="margin:0 0 8px;">
                <button type="button" class="button button-primary bkash-override-btn" data-action="capture" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-nonce="<?php echo esc_attr( $click_nonce ); ?>">Capture</button>
                <button type="button" class="button button-secondary bkash-override-btn" data-action="void" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-nonce="<?php echo esc_attr( $click_nonce ); ?>">Void</button>
            </p>
            <div class="bkash-override-result" style="margin-top:8px;"></div>
        </div>
        <script>(function(){
            var ajaxUrl = '<?php echo $ajax_url; ?>';
            document.addEventListener('click', function(e){
                var t = e.target;
                if(!t.classList || !t.classList.contains('bkash-override-btn')) return;
                e.preventDefault();
                var action = t.getAttribute('data-action');
                var orderId = t.getAttribute('data-order-id');
                var nonce = t.getAttribute('data-nonce');
                var xhrAction = action === 'capture' ? 'bkash_capture_ajax' : 'bkash_void_ajax';
                var body = 'order_id=' + encodeURIComponent(orderId) + '&_ajax_nonce=' + encodeURIComponent(nonce);
                var container = t.closest('div');
                // disable buttons while processing
                var buttons = container.querySelectorAll('.bkash-override-btn');
                buttons.forEach(function(b){ b.classList.add('disabled'); b.setAttribute('aria-disabled','true'); });
                fetch( ajaxUrl + '?action=' + xhrAction, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                } ).then(function(r){ return r.json(); }).then(function(json){
                    var res = container.querySelector('.bkash-override-result');
                    if(json && json.success){
                        // Update UI: hide buttons and show success alert
                        container.style.display = 'none';
                        var alert = document.createElement('div');
                        alert.className = 'notice notice-success';
                        alert.style.padding = '6px';
                        alert.style.margin = '0';
                        alert.innerText = 'bKash ' + action + ' succeeded';
                        document.body.insertBefore(alert, document.body.firstChild);
                        // Try to update order status display if present
                        var newStatus = action === 'capture' ? 'processing' : 'cancelled';
                        var statusSelect = document.querySelector('#order_status');
                        if(statusSelect){ try{ statusSelect.value = 'wc-' + newStatus; statusSelect.dispatchEvent(new Event('change')); }catch(e){} }
                        var statusText = document.querySelector('.order_status, .woocommerce-order-status, #order_status_text');
                        if(statusText){ try{ statusText.textContent = newStatus; }catch(e){} }
                    } else {
                        res.innerHTML = '<div class="notice notice-error" style="padding:6px;margin:0;">bKash ' + action + ' failed</div>';
                        buttons.forEach(function(b){ b.classList.remove('disabled'); b.removeAttribute('aria-disabled'); });
                    }
                }).catch(function(){
                    var res = container.querySelector('.bkash-override-result');
                    res.innerHTML = '<div class="notice notice-error" style="padding:6px;margin:0;">Network error</div>';
                    buttons.forEach(function(b){ b.classList.remove('disabled'); b.removeAttribute('aria-disabled'); });
                });
            }, false);
        })();</script>
        <?php
    }

    /**
     * Render buttons on the WooCommerce Orders single-view (wc-admin) page.
     */
    public function maybe_render_orders_list_buttons() {
        // Debug logging to help diagnose why buttons aren't showing
        $log_context = array( 'source' => BKASH_FW_PLUGIN_SLUG );
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->debug( 'maybe_render_orders_list_buttons called', array_merge( $log_context, array( 'GET' => $_GET ) ) );
        } else {
            error_log( '[bKash] maybe_render_orders_list_buttons called; GET=' . json_encode( $_GET ) );
        }

        global $pagenow;
        $order_id = 0;
        // Classic order edit screen (post.php?post=123)
        if ( isset( $pagenow ) && $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
            $order_id = absint( $_GET['post'] );
        } else {
            // WC admin orders editor (admin.php?page=wc-orders&action=edit&id=123)
            if ( empty( $_GET['page'] ) || $_GET['page'] !== 'wc-orders' ) {
                if ( isset( $logger ) ) {
                    $logger->debug( 'page param mismatch', array_merge( $log_context, array( 'page' => $_GET['page'] ?? null ) ) );
                } else {
                    error_log( '[bKash] page param mismatch: ' . ( $_GET['page'] ?? '' ) );
                }
                return;
            }
            if ( empty( $_GET['action'] ) || $_GET['action'] !== 'edit' ) {
                if ( isset( $logger ) ) {
                    $logger->debug( 'action param mismatch', array_merge( $log_context, array( 'action' => $_GET['action'] ?? null ) ) );
                } else {
                    error_log( '[bKash] action param mismatch: ' . ( $_GET['action'] ?? '' ) );
                }
                return;
            }
            $order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
            if ( ! $order_id ) {
                if ( isset( $logger ) ) {
                    $logger->debug( 'missing order_id', $log_context );
                } else {
                    error_log( '[bKash] missing order_id' );
                }
                return;
            }
        }
        

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            if ( isset( $logger ) ) {
                $logger->debug( 'order not found', array_merge( $log_context, array( 'order_id' => $order_id ) ) );
            } else {
                error_log( '[bKash] order not found: ' . $order_id );
            }
            return;
        }

        if ( $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            if ( isset( $logger ) ) {
                $logger->debug( 'payment method mismatch', array_merge( $log_context, array( 'order_id' => $order_id, 'payment_method' => $order->get_payment_method() ) ) );
            } else {
                error_log( '[bKash] payment method mismatch for order ' . $order_id . ': ' . $order->get_payment_method() );
            }
            return;
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction ) {
            if ( isset( $logger ) ) {
                $logger->debug( 'transaction not found', array_merge( $log_context, array( 'order_id' => $order_id ) ) );
            } else {
                error_log( '[bKash] transaction not found for order ' . $order_id );
            }
            return;
        }

        $intent_lc = strtolower( (string) $transaction->getIntent() );
        $status_lc = strtolower( (string) $transaction->getStatus() );
        if ( ! $order->has_status( 'on-hold' ) || ! in_array( $intent_lc, array( 'authorize', 'authorization' ), true ) || $status_lc !== 'authorized' ) {
            if ( isset( $logger ) ) {
                $logger->debug( 'conditions not met', array_merge( $log_context, array( 'order_id' => $order_id, 'order_status' => $order->get_status(), 'intent' => $intent_lc, 'transaction_status' => $status_lc ) ) );
            } else {
                error_log( '[bKash] conditions not met for order ' . $order_id . ' status=' . $order->get_status() . ' intent=' . $intent_lc . ' trx_status=' . $status_lc );
            }
            return;
        }

        $capture_url = wp_nonce_url( admin_url( 'admin-post.php?action=bkash_capture&order_id=' . $order_id ), 'bkash_capture_' . $order_id );
        $void_url    = wp_nonce_url( admin_url( 'admin-post.php?action=bkash_void&order_id=' . $order_id ), 'bkash_void_' . $order_id );

        $click_nonce = wp_create_nonce( 'bkash_admin_actions' );

        // Inject the postbox HTML so external JS can attach behavior if needed.
        ?>
        <div id="bkash-order-actions-card" class="postbox" style="margin-top:8px;">
            <div class="postbox-header">
                <h2 class="hndle ui-sortable-handle"><span>bKash Actions</span></h2>
                <div class="handle-actions hide-if-no-js">
                    <button type="button" class="handle-order-higher" aria-disabled="false"><span class="screen-reader-text">Move up</span><span class="order-higher-indicator" aria-hidden="true"></span></button>
                    <button type="button" class="handle-order-lower" aria-disabled="false"><span class="screen-reader-text">Move down</span><span class="order-lower-indicator" aria-hidden="true"></span></button>
                    <button type="button" class="handlediv" aria-expanded="true"><span class="screen-reader-text">Toggle panel: bKash Actions</span><span class="toggle-indicator" aria-hidden="true"></span></button>
                </div>
            </div>
            <div class="inside">
                <p style="margin:0 0 8px;">
                    <select class="bkash-action-select" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-nonce="<?php echo esc_attr( $click_nonce ); ?>" style="margin-right:8px;">
                        <option value="">Select action</option>
                        <option value="capture">Capture</option>
                        <option value="void">Void</option>
                    </select>
                    <button class="button button-primary bkash-action-run">Run</button>
                </p>
            </div>
        </div>
        <?php
    }

    public function add_meta_box() {
        add_meta_box(
            'bkash-order-actions',
            'bKash Actions',
            array( $this, 'render_meta_box' ),
            'shop_order',
            'side',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        $order = wc_get_order( $post->ID );
        if ( ! $order ) {
            echo '<p>Order not found.</p>';
            return;
        }

        if ( $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            echo '<p>Not a bKash order. Payment method: ' . esc_html( $order->get_payment_method() ) . '</p>';
            return;
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order->get_id() );
        $intent = $transaction ? $transaction->getIntent() : '';
        $status = $transaction ? $transaction->getStatus() : '';

        // Determine whether capture/void should be enabled
        $intent_lc = strtolower( (string) $intent );
        $status_lc = strtolower( (string) $status );
        $enabled = $order->has_status( 'on-hold' ) && ! empty( $transaction ) && in_array( $intent_lc, array( 'authorize', 'authorization' ), true ) && $status_lc === 'authorized';

        // Always show the meta box buttons for bKash orders; disable them when not available
        $capture_url = wp_nonce_url( admin_url( 'admin-post.php?action=bkash_capture&order_id=' . $order->get_id() ), 'bkash_capture_' . $order->get_id() );
        $void_url    = wp_nonce_url( admin_url( 'admin-post.php?action=bkash_void&order_id=' . $order->get_id() ), 'bkash_void_' . $order->get_id() );

        $capture_classes = 'button button-primary bkash-action-btn';
        $void_classes = 'button button-secondary bkash-action-btn';
        $capture_attrs = '';
        $void_attrs = '';
        if ( ! $enabled ) {
            $capture_classes .= ' disabled';
            $void_classes .= ' disabled';
            $capture_attrs = ' aria-disabled="true" title="Capture not available"';
            $void_attrs = ' aria-disabled="true" title="Void not available"';
        }

        echo '<p>';
        echo '<a class="' . esc_attr( $capture_classes ) . '" href="' . esc_url( $capture_url ) . '"' . $capture_attrs . '>Capture</a> ';
        echo '<a class="' . esc_attr( $void_classes ) . '" href="' . esc_url( $void_url ) . '"' . $void_attrs . '>Void</a>';
        echo '</p>';
        if ( $enabled ) {
            echo '<p style="font-size:12px;color:#666;margin-top:6px;">Use these to manually capture or void an authorized bKash payment.</p>';
        } else {
            echo '<p style="font-size:12px;color:#666;margin-top:6px;">Capture/Void not available for this order (buttons disabled).</p>';
        }
    }

    private function redirect_back_with_message( $order_id, $message, $type = 'success' ) {
        $url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
        $url = add_query_arg( 'bkash_notice', urlencode( $message ), $url );
        wp_safe_redirect( $url );
        exit;
    }

    public function handle_capture() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_die( 'Missing order id' );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bkash_capture_' . $order_id ) ) {
            wp_die( 'Invalid nonce' );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            $this->redirect_back_with_message( $order_id, 'Order is not a bKash order', 'error' );
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction || $transaction->getStatus() !== 'Authorized' ) {
            $this->redirect_back_with_message( $order_id, 'Transaction not authorized or not found', 'error' );
        }

        $paymentID = $transaction->getPaymentID();

        // Enqueue Action Scheduler job if available
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'bkash_capture_job', array( 'order_id' => $order_id, 'payment_id' => $paymentID ) );
            $this->redirect_back_with_message( $order_id, 'Capture job enqueued', 'success' );
        }


        // Fallback: call directly
        $comm = new ApiComm();
        $resp = $comm->capturePayment( $paymentID );

        $paymentResp = Operations::processResponse( $resp, 'trxID' );

        if ( is_array( $paymentResp ) ) {
            $status = $paymentResp['transactionStatus'] ?? 'NO_STATUS_EXECUTE';
            $updated = $trxObj->update(
                array(
                    'status' => $status,
                    'trx_id' => $paymentResp['trxID'] ?? '',
                ),
                array( 'trx_id' => $transaction->getTrxID() )
            );

            if ( $updated && isset( $paymentResp['trxID'] ) && ! empty( $paymentResp['trxID'] ) ) {
                // Payment complete.
                if ( $paymentResp['transactionStatus'] === 'Authorized' ) {
                    $order->update_status( 'on-hold' );
                } elseif ( $paymentResp['transactionStatus'] === 'Completed' ) {
                    // Mark order as processing — merchant will complete when fulfilled
                    $order->update_status( 'processing', 'bKash capture completed (manual).' );
                    if ( method_exists( $order, 'save' ) ) {
                        $order->save();
                    }
                } else {
                    $order->update_status( 'pending' );
                }

                if ( method_exists( $order, 'set_transaction_id' ) ) {
                    $order->set_transaction_id( $paymentResp['trxID'] );
                } else {
                    $order->update_meta_data( '_transaction_id', $paymentResp['trxID'] );
                }

                $completedRaw = $paymentResp['completedTime'] ?? $paymentResp['updateTime'] ?? $paymentResp['createTime'] ?? '';
                $completedDatetime = '';
                if ( ! empty( $completedRaw ) ) {
                    $clean = preg_replace( '/:(\d{3})/', '', $completedRaw );
                    $clean = str_replace( 'GMT', '', $clean );
                    $ts = strtotime( $clean );
                    if ( $ts !== false ) {
                        $completedDatetime = date( 'Y-m-d H:i:s', $ts );
                    }
                }

                $note = sprintf( 'bKash PGW payment captured (ID: %s)', $paymentResp['trxID'] );
                if ( $completedDatetime ) {
                    $note .= ' — Completed at: ' . $completedDatetime;
                    $order->update_meta_data( '_bkash_completed_time', $completedDatetime );
                }
                $order->add_order_note( $note );

                // Reduce stock levels on completed
                if ( $paymentResp['transactionStatus'] === 'Completed' ) {
                    wc_reduce_stock_levels( $order_id );
                }

                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }

                $trxObj->update( array( 'status' => $paymentResp['transactionStatus'] ?? 'Completed' ), array( 'trx_id' => $transaction->getTrxID() ) );
                $this->redirect_back_with_message( $order_id, 'Payment captured successfully', 'success' );
            }
        }

        $this->redirect_back_with_message( $order_id, 'Capture failed', 'error' );
    }

    public function handle_void() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_die( 'Missing order id' );
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bkash_void_' . $order_id ) ) {
            wp_die( 'Invalid nonce' );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            $this->redirect_back_with_message( $order_id, 'Order is not a bKash order', 'error' );
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction || $transaction->getStatus() !== 'Authorized' ) {
            $this->redirect_back_with_message( $order_id, 'Transaction not authorized or not found', 'error' );
        }

        $paymentID = $transaction->getPaymentID();

        // Enqueue Action Scheduler job if available
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'bkash_void_job', array( 'order_id' => $order_id, 'payment_id' => $paymentID ) );
            $this->redirect_back_with_message( $order_id, 'Void job enqueued', 'success' );
        }

        // Fallback: call directly
        $comm = new ApiComm();
        $resp = $comm->voidPayment( $paymentID );

        $paymentResp = Operations::processResponse( $resp, 'trxID' );
        if ( is_array( $paymentResp ) ) {
            // update transaction
            $trxObj->update(
                array(
                    'status' => $paymentResp['transactionStatus'] ?? 'Cancelled',
                    'trx_id' => $paymentResp['trxID'] ?? '',
                ),
                array( 'trx_id' => $transaction->getTrxID() )
            );

            if ( $order ) {
                $order->add_order_note( 'bKash void requested (manual).' );
                $order->update_status( 'cancelled' );
                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }
            }

            $this->redirect_back_with_message( $order_id, 'Payment voided successfully', 'success' );
        }

        $this->redirect_back_with_message( $order_id, 'Void failed', 'error' );
    }

    /**
     * AJAX handler to log admin button clicks
     */
    public function ajax_log_click() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( 'no-permission', 403 );
        }

        $nonce = $_REQUEST['_ajax_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'bkash_admin_actions' ) ) {
            wp_send_json_error( 'invalid-nonce', 400 );
        }

        $order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
        $action_type = sanitize_text_field( $_REQUEST['action_type'] ?? '' );

        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->info( 'bKash admin button clicked: ' . $action_type . ' for order ' . $order_id, array( 'source' => BKASH_FW_PLUGIN_SLUG ) );
        } else {
            error_log( '[bKash] admin button clicked: ' . $action_type . ' for order ' . $order_id );
        }

        wp_send_json_success();
    }

    public function process_capture_job( $args ) {
        $order_id = $args['order_id'] ?? 0;
        $paymentID = $args['payment_id'] ?? '';
        if ( ! $order_id || empty( $paymentID ) ) {
            return;
        }
        $comm = new ApiComm();
        $resp = $comm->capturePayment( $paymentID );

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        $order = wc_get_order( $order_id );

        $paymentResp = Operations::processResponse( $resp, 'trxID' );
        if ( is_array( $paymentResp ) ) {
            $status = $paymentResp['transactionStatus'] ?? 'NO_STATUS_EXECUTE';
            $updated = $trxObj->update(
                array(
                    'status' => $status,
                    'trx_id' => $paymentResp['trxID'] ?? '',
                ),
                array( 'trx_id' => $transaction ? $transaction->getTrxID() : '' )
            );

            if ( $updated && isset( $paymentResp['trxID'] ) && ! empty( $paymentResp['trxID'] ) ) {
                if ( $order ) {
                    $order->add_order_note( 'bKash capture completed (async).' );
                    if ( $paymentResp['transactionStatus'] === 'Completed' ) {
                        $order->update_status( 'processing', 'bKash capture completed (async).' );
                    } elseif ( $paymentResp['transactionStatus'] === 'Authorized' ) {
                        $order->update_status( 'on-hold' );
                    } else {
                        $order->update_status( 'pending' );
                    }
                }
                if ( $transaction ) {
                    $trxObj->update( array( 'status' => $paymentResp['transactionStatus'] ?? 'Completed' ), array( 'trx_id' => $transaction->getTrxID() ) );
                }
                if ( $paymentResp['transactionStatus'] === 'Completed' ) {
                    wc_reduce_stock_levels( $order_id );
                }
                if ( $order && method_exists( $order, 'save' ) ) {
                    $order->save();
                }
            }
        } else {
            if ( $order ) {
                $order->add_order_note( 'bKash capture failed (async).' );
                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }
            }
        }
    }

    public function process_void_job( $args ) {
        $order_id = $args['order_id'] ?? 0;
        $paymentID = $args['payment_id'] ?? '';
        if ( ! $order_id || empty( $paymentID ) ) {
            return;
        }

        $comm = new ApiComm();
        $resp = $comm->voidPayment( $paymentID );

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        $order = wc_get_order( $order_id );

        $paymentResp = Operations::processResponse( $resp, 'trxID' );
        if ( is_array( $paymentResp ) ) {
            $trxObj->update(
                array(
                    'status' => $paymentResp['transactionStatus'] ?? 'Cancelled',
                    'trx_id' => $paymentResp['trxID'] ?? '',
                ),
                array( 'trx_id' => $transaction ? $transaction->getTrxID() : '' )
            );

            if ( $order ) {
                $order->add_order_note( 'bKash void completed (async).' );
                $order->update_status( 'cancelled' );
                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }
            }
        } else {
            if ( $order ) {
                $order->add_order_note( 'bKash void failed (async).' );
                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }
            }
        }
    }

    /**
     * AJAX-only capture (no page reload)
     */
    public function ajax_capture() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( 'no-permission', 403 );
        }

        $nonce = $_REQUEST['_ajax_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'bkash_admin_actions' ) ) {
            wp_send_json_error( 'invalid-nonce', 400 );
        }

        $order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( 'missing-order', 400 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            wp_send_json_error( 'not-bkash-order', 400 );
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction || $transaction->getStatus() !== 'Authorized' ) {
            wp_send_json_error( 'not-authorized', 400 );
        }

        $paymentID = $transaction->getPaymentID();
        $comm = new ApiComm();
        $resp = $comm->capturePayment( $paymentID );

        $paymentResp = Operations::processResponse( $resp, 'trxID' );
        if ( is_array( $paymentResp ) ) {
            $status = $paymentResp['transactionStatus'] ?? 'NO_STATUS_EXECUTE';
            $updated = $trxObj->update(
                array(
                    'status' => $status,
                    'trx_id' => $paymentResp['trxID'] ?? '',
                ),
                array( 'trx_id' => $transaction->getTrxID() )
            );

            if ( $updated && isset( $paymentResp['trxID'] ) && ! empty( $paymentResp['trxID'] ) ) {
                if ( $order ) {
                    $order->add_order_note( 'bKash capture requested.' );
                    if ( $paymentResp['transactionStatus'] === 'Completed' ) {
                        $order->update_status( 'processing', 'bKash capture completed.' );
                    } elseif ( $paymentResp['transactionStatus'] === 'Authorized' ) {
                        $order->update_status( 'on-hold' );
                    } else {
                        $order->update_status( 'pending' );
                    }
                }

                if ( method_exists( $order, 'set_transaction_id' ) ) {
                    $order->set_transaction_id( $paymentResp['trxID'] );
                } else {
                    $order->update_meta_data( '_transaction_id', $paymentResp['trxID'] );
                }

                if ( $paymentResp['transactionStatus'] === 'Completed' ) {
                    wc_reduce_stock_levels( $order_id );
                }

                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }

                if ( $transaction ) {
                    $trxObj->update( array( 'status' => $paymentResp['transactionStatus'] ?? 'Completed' ), array( 'trx_id' => $transaction->getTrxID() ) );
                }

                wp_send_json_success();
            }
        }

        $debug_payload = array(
            'error' => $paymentResp,
            'api_status' => is_array( $resp ) && isset( $resp['status_code'] ) ? $resp['status_code'] : null,
            'api_response' => null,
        );
        if ( is_array( $resp ) && isset( $resp['response'] ) ) {
            $decoded = json_decode( $resp['response'], true );
            $debug_payload['api_response'] = $decoded !== null ? $decoded : $resp['response'];
        }
        wp_send_json_error( $debug_payload, 500 );
    }

    /**
     * AJAX-only void (no page reload)
     */
    public function ajax_void() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( 'no-permission', 403 );
        }

        $nonce = $_REQUEST['_ajax_nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'bkash_admin_actions' ) ) {
            wp_send_json_error( 'invalid-nonce', 400 );
        }

        $order_id = isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( 'missing-order', 400 );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_payment_method() !== BKASH_FW_PLUGIN_SLUG ) {
            wp_send_json_error( 'not-bkash-order', 400 );
        }

        $trxObj = new Transaction();
        $transaction = $trxObj->getTransactionByOrderId( $order_id );
        if ( ! $transaction || $transaction->getStatus() !== 'Authorized' ) {
            wp_send_json_error( 'not-authorized', 400 );
        }

        $paymentID = $transaction->getPaymentID();
        $comm = new ApiComm();
        $resp = $comm->voidPayment( $paymentID );

        $paymentResp = Operations::processResponse( $resp, 'trxID' );
        if ( is_array( $paymentResp ) ) {
            $trxObj->update(
                array(
                    'status' => $paymentResp['transactionStatus'] ?? 'Cancelled',
                    'trx_id' => $paymentResp['trxID'] ?? '',
                ),
                array( 'trx_id' => $transaction->getTrxID() )
            );

            if ( $order ) {
                $order->add_order_note( 'bKash void requested.' );
                $order->update_status( 'cancelled' );
                if ( method_exists( $order, 'save' ) ) {
                    $order->save();
                }
            }

            wp_send_json_success();
        }

        $debug_payload = array(
            'error' => $paymentResp,
            'api_status' => is_array( $resp ) && isset( $resp['status_code'] ) ? $resp['status_code'] : null,
            'api_response' => null,
        );
        if ( is_array( $resp ) && isset( $resp['response'] ) ) {
            $decoded = json_decode( $resp['response'], true );
            $debug_payload['api_response'] = $decoded !== null ? $decoded : $resp['response'];
        }
        wp_send_json_error( $debug_payload, 500 );
    }
}

// Initialize in admin area
add_action( 'plugins_loaded', function() {
    if ( is_admin() ) {
        $oa = new OrderActions();
    }
} );

