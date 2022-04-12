<?php
function keyToLabel( $str, $separator = "_" ) {
	$str = str_replace( $separator, " ", $str );

	return ucwords( $str );
}

function ifRefundValueIsPresent( $row, $column ) {
	return isset( $column[0] ) && str_contains( strtolower( $column[0] ), "refund" )
	       && ! empty( $row->{$column[0]} );
}

?>

    <div class="wrap abs">
        <h2><?php esc_html_e( $title ?? 'List', "bkash-for-woocommerce" ); ?></h2>

        <!-- Search Form -->
        <div class="tablenav top">
            <div class="alignleft actions">

                <form action="#" method="GET">
					<?php
					if ( isset( $filters ) && count( $filters ) > 0 ) {
						foreach ( $filters as $key => $filter ) {
							$old_input = isset( $_GET[ $key ] ) ? sanitize_text_field( $_GET[ $key ] ) : "";
							?>
                            <input
                                    type='text'
                                    name='<?php esc_attr_e( $key, "bkash-for-woocommerce" ); ?>'
                                    value='<?php esc_attr_e( $old_input, "bkash-for-woocommerce" ); ?>'
                                    placeholder='<?php esc_attr_e( $filter, "bkash-for-woocommerce" ); ?>'/>
							<?php
						}
					}

					$page_name = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
					?>
                    <input type='hidden' name='page'
                           value='<?php esc_attr_e( $page_name, "bkash-for-woocommerce" ); ?>'/>
                    <button type="submit">Search</button>
                </form>


            </div>
            <br class="clear">
        </div>

        <!-- Table -->
        <table id="transaction-list-table" class='wp-list-table widefat fixed striped posts'
               aria-describedby="<?php esc_attr_e( $title ); ?>">
            <!-- Column Headers -->
            <tr>
				<?php
				if ( isset( $columns ) ) {
					foreach ( array_keys( $columns ) as $table_head ) {
						?>
                        <th class='manage-column ss-list-width' scope='col'>
							<?php esc_html_e( $table_head ); ?>
                        </th>
						<?php
					}

					if ( isset( $actions ) && count( $actions ) > 0 ) {
						?>
                        <th class='manage-column ss-list-width' scope='col'>
                            Actions
                        </th>
						<?php
					}
				}
				?>
            </tr>

			<?php
			if ( isset( $rows ) && count( (array) $rows ) > 0 ) {
				foreach ( $rows as $row ) { ?>
                    <!-- Items -->
                    <tr>
						<?php
						foreach ( $columns as $column ) {
							// if want to show multiple value in a single column
							if ( is_array( $column ) ) { ?>
                                <td class='manage-column ss-list-width'>
									<?php
									if ( ifRefundValueIsPresent($row, $column)) {
										?>
                                        <span class="bKash-chip">Refunded</span>
										<?php
									}

									foreach ( $column as $item ) {
										if ( ! empty( $row->{$item} ) ) {
											?>
                                            <p>
												<?php
												$constructed_value = keyToLabel( $item ) . ": " . $row->{$item};
												esc_html_e( $constructed_value, "bkash-for-woocommerce" );
												?>
                                            </p>
											<?php
										}
									}
									?>
                                </td>
								<?php
							} else { // single value in a column
								?>
                                <td class='manage-column ss-list-width'>
									<?php
									if ( str_contains( strtolower( $column ), "status" ) ) {
										?>
                                        <span class="bKash-chip"><?php esc_html_e( $row->{$column}, "bkash-for-woocommerce" ); ?></span>
										<?php
									} else {
										esc_html_e( $row->{$column}, "bkash-for-woocommerce" );
									}
									?>
                                </td>
								<?php
							}
						}
						?>
                        <!-- Action Buttons -->
						<?php if ( isset( $actions ) && count( $actions ) > 0 ) { ?>
                            <td class='manage-column ss-list-width'>
								<?php
								foreach ( $actions as $action ) {
									?>
                                    <a
										<?php
										if ( isset( $action['confirm'] ) && $action['confirm'] ) {
											echo 'onclick="return confirm(\'Are you sure to do this?\');"';
										}
										?>
                                            href="<?php echo esc_url(
												admin_url( 'admin.php?page=' . BKASH_FW_ADMIN_PAGE_SLUG . '/' . ( $action['page'] ?? '' )
												           . '&action=' . ( $action['action'] ?? '' ) . '&id=' . $row->ID )
											); ?>">
										<?php esc_html_e( $action['title'] ?? '' ) ?>
                                    </a>
									<?php
								}
								?>
                            </td>
						<?php } ?>
                    </tr>
				<?php }
			} else {
				echo "<tr><td colspan='" . count( $columns ) . "'>No records found</td></tr>";
			} ?>
        </table>
    </div>

<?php
if ( isset( $page_links ) && $page_links ) {
	?>
    <div class="tablenav pagination-links" style="width: 99%;">
        <div class="tablenav-pages" style="margin: 1em 0"><?= $page_links ?></div>
    </div>
	<?php
}
?>