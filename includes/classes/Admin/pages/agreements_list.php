<?php

$action = $_REQUEST['action'] ?? '';

if ($action === 'cancel') {
    $id = $_REQUEST['id'] ?? null;
    if ($id) {
        $agreementObj = new \bKash\PGW\Models\Agreement();
        $agreement = $agreementObj->getAgreement('','',$id);
        if ($agreement) {
            $comm = new \bKash\PGW\ApiComm();
            $cancelAgreement = $comm->agreementCancel($agreement->getAgreementID());

            if (isset($cancelAgreement['status_code']) && $cancelAgreement['status_code'] === 200) {
                $response = isset($cancelAgreement['response']) && is_string($cancelAgreement['response']) ? json_decode($cancelAgreement['response'], true) : [];

                if (isset($response['agreementStatus']) && $response['agreementStatus'] === 'Cancelled') {
                    // Cancelled

                    $deleteAgreement = $agreementObj->delete('', $id);
                    // echo $agreementObj->errorMessage;
                    if($deleteAgreement) {
                        echo "Agreement Deleted!";
                    } else {
                        echo "Agreement cancelled but could not delete from db";
                    }

                } else {
                    echo "Agreement status was not present. " . json_encode($response);
                }
            } else{
                echo " Server response was not ok. " . json_encode($cancelAgreement);
            }
        } else {
            echo "No agreement found related with this ID";
        }
    } else {
        echo "ID was not present to cancel";
    }
}


global $wpdb;
$table_name = $wpdb->prefix . "bkash_agreement_mapping";

$pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;

$limit = 10; // number of rows in page
$offset = ($pagenum - 1) * $limit;
$total = $wpdb->get_var("select count(*) as total from $table_name");
$num_of_pages = ceil($total / $limit);

$rows = $wpdb->get_results("SELECT * from $table_name limit  $offset, $limit");
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
        <h2>All bKash Agreements</h2>
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
                <th class="manage-column ss-list-width">PHONE</th>
                <th class="manage-column ss-list-width">USERID</th>
                <th class="manage-column ss-list-width">AGREEMENT TOKEN</th>
                <th class="manage-column ss-list-width">DATETIME</th>
                <th>&nbsp;</th>
                <th>&nbsp;</th>
            </tr>
            <?php
            if ($rowcount > 0) {
                foreach ($rows as $row) { ?>
                    <tr>
                        <td class="manage-column ss-list-width"><?php echo $row->ID; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->phone; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->user_id; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->agreement_token; ?></td>
                        <td class="manage-column ss-list-width"><?php echo $row->datetime; ?></td>
                        <td>
                            <a onclick="return confirm('Are you sure to cancel this?');" href="<?php echo admin_url('admin.php?page=' . $this->slug . '/agreements&action=cancel&id=' . $row->ID); ?>">Cancel
                                Agreement</a></td>


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
    echo '<div class="tablenav" style="width: 99%;"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
}