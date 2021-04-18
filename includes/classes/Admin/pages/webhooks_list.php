<?php
global $wpdb;
$table_name = $wpdb->prefix . "bkash_webhooks";

$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;

$limit = 10; // number of rows in page
$offset = ( $pagenum - 1 ) * $limit;
$total = $wpdb->get_var( "select count(*) as total from $table_name" );
$num_of_pages = ceil( $total / $limit );

$rows = $wpdb->get_results( "SELECT * from $table_name limit  $offset, $limit" );
$rowcount = $wpdb->num_rows;

?>
    <link type="text/css" href="<?php echo WP_PLUGIN_URL; ?>mywp/sinetiks-schools/style-admin.css" rel="stylesheet" />

    <div class="wrap abs">
        <h2>All bKash Webhooks</h2>
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
                <th class="manage-column ss-list-width">TRXID</th>
                <th class="manage-column ss-list-width">AMOUNT</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
            </tr>
            <?php
            if($rowcount>0){
                foreach ($rows as $row) { ?>
                    <tr>
                        <td class="manage-column ss-list-width"><?php echo $row->ID; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->trx_id; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->amount; ?></td>
                        <td><a href="<?php echo admin_url('admin.php?page=wp_schools_update&id=' . $row->id); ?>">Update</a></td>
                        <td><a href="<?php echo admin_url('admin.php?page=wp_schools_delete&id=' . $row->id); ?>">Delete</a></td>


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