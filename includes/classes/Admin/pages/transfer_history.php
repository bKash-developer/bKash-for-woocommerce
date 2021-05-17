<?php
global $wpdb;
$table_name = $wpdb->prefix . "bkash_transfers";

$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;

$limit = 10; // number of rows in page
$offset = ( $pagenum - 1 ) * $limit;
$total = $wpdb->get_var( "select count(*) as total from $table_name" );
$num_of_pages = ceil( $total / $limit );

$rows = $wpdb->get_results( "SELECT * from $table_name ORDER BY id DESC limit  $offset, $limit" );
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
        <h2>Transfer History</h2>
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
                <th class="manage-column ss-list-width">SENT TO (bKash Personal)</th>
                <th class="manage-column ss-list-width">Amount</th>
                <th class="manage-column ss-list-width">TRANSACTION ID</th>
                <th class="manage-column ss-list-width">INVOICE NO</th>
                <th class="manage-column ss-list-width">STATUS</th>
                <th class="manage-column ss-list-width">B2C FEES</th>
                <th class="manage-column ss-list-width">INITIATION TIME</th>
                <th class="manage-column ss-list-width">COMPLETION TIME</th>
            </tr>
            <?php
            if($rowcount>0){
                foreach ($rows as $row) { ?>
                    <tr>
                        <td class="manage-column ss-list-width"><?php echo $row->ID ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->receiver ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo ($row->amount ?? '') .' '. ($row->currency ?? ''); ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->trx_id ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->merchant_invoice_no ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->transactionStatus ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->b2cFee ?? ''; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->initiationTime; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->completedTime; ?></td>

                    </tr>
                <?php } }else{
                echo "<tr><td cols=an='5'>No records found</td></tr>";
            } ?>
        </table>
    </div>
<?php

$page_links = paginate_links( array(
    'base' => add_query_arg( 'pagenum', '%#%' ),
    'format' => '',
    'prev_text' => __( '&laquo;', 'text-domain' ),
    'next_text' => __( '&raquo;', 'text-domain' ),
    'total' => $num_of_pages,
    'current' => $pagenum
) );

if ( $page_links ) {
    echo '<div class="tablenav" style="width: 99%;"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
}