<?php
global $wpdb;
$table_name = $wpdb->prefix . "bkash_transactions";

$pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;

$limit = 10; // number of rows in page
$offset = ($pagenum - 1) * $limit;
$total = $wpdb->get_var("select count(*) as total from $table_name");
$num_of_pages = ceil($total / $limit);

$rows = $wpdb->get_results("SELECT * from $table_name ORDER BY id DESC limit  $offset, $limit");
$rowcount = $wpdb->num_rows;

?>
    <style>
        .pagination-links .page-numbers {
            font-size: 15px;
            padding: 5px 10px;
            border: 1px solid #b3b3b3;
            text-decoration: none;
        }

        .pagination-links .page-numbers.current {
            font-weight: bold;
            background: #fff;
            color: #e2136e;
            border: 1px solid #999;
        }
    </style>

    <div class="wrap abs">
        <h2>All bKash Transactions</h2>
        <div class="tablenav top">
            <div class="alignleft actions">
            </div>
            <br class="clear">
        </div>
        <?php
        $path_array = wp_upload_dir()['baseurl']; // wp_upload_dir has diffrent types of array I am used 'baseurl' for path

        ?>
        <table class='wp-list-table widefat fixed striped posts'>
            <tr>
                <th class="manage-column ss-list-width">ID</th>
                <th class="manage-column ss-list-width">ORDER ID</th>
                <th class="manage-column ss-list-width">INVOICE ID</th>
                <th class="manage-column ss-list-width">PAYMENT ID</th>
                <th class="manage-column ss-list-width">TRANSACTION ID</th>
                <th class="manage-column ss-list-width">AMOUNT</th>
                <th class="manage-column ss-list-width">INTEGRATION TYPE</th>
                <th class="manage-column ss-list-width">INTENT</th>
                <th class="manage-column ss-list-width">MODE</th>
                <th class="manage-column ss-list-width">REFUNDED?</th>
                <th class="manage-column ss-list-width">REFUND AMOUNT</th>
                <th class="manage-column ss-list-width">STATUS</th>
                <th class="manage-column ss-list-width">DATETIME</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
            </tr>

            <?php
            if ($rowcount > 0) {
                foreach ($rows as $row) { ?>
                    <tr>
                        <td class="manage-column ss-list-width"><?php echo $row->ID ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->order_id ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->invoice_id ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->payment_id ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->trx_id ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo ($row->amount ?? '') . ' ' . ($row->currency ?? ''); ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->integration_type; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->intent; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->mode; ?></td>
                        <td class="manage-column ss-list-width"><?php echo !empty($row->refund_id) ? "YES ($row->refund_id)" : 'NO'; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->refund_amount; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->status; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->datetime; ?></td>
                        <?php if (isset($row->trx_id) && !empty($row->trx_id)) { ?>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=bkash_admin_menu_120beta/search&trxid=' . $row->trx_id); ?>">Search</a>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=bkash_admin_menu_120beta/refund&fill_trx_id=' . $row->trx_id); ?>">Refund</a>
                            </td>
                        <?php } ?>

                    </tr>
                <?php }
            } else {
                echo "<tr><td cols=an='5'>No records found</td></tr>";
            } ?>
        </table>
    </div>
<?php

$page_links = paginate_links(array(
    'base' => add_query_arg('pagenum', '%#%'),
    'format' => '',
    'prev_text' => __('&laquo;', 'text-domain'),
    'next_text' => __('&raquo;', 'text-domain'),
    'total' => $num_of_pages,
    'current' => $pagenum
));

if ($page_links) {
    echo '<div class="tablenav pagination-links" style="width: 99%;"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
}