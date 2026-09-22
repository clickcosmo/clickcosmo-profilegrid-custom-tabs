<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class CC_ProfileGrid_Custom_Tabs {
    private const OPTION_KEY = 'ccpgt_tabs';
    private const PAGE_SLUG  = 'ccpgt_custom_tabs';
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
        add_action( 'profile_magic_setting_option', array( $this, 'render_settings_tile' ) );
        add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( CCPGT_FILE ), array( $this, 'plugin_action_links' ) );

        if ( $this->profilegrid_available() ) {
            add_filter( 'pm_profile_tabs', array( $this, 'register_tabs' ), 50 );
            add_action( 'profile_magic_profile_tab_link', array( $this, 'render_tab_link' ), 10, 5 );
            add_action( 'profile_magic_profile_tab_extension_content', array( $this, 'render_tab_content' ), 10, 5 );
            add_filter( 'pg_user_privacy_fields_visibility_qry', array( $this, 'exclude_custom_tab_fields_from_about' ), 20, 7 );
        }
    }

    private function profilegrid_available() {
        return class_exists( 'PM_DBhandler' ) && class_exists( 'PM_request' );
    }

    public function dependency_notice() {
        if ( ! current_user_can( 'activate_plugins' ) || $this->profilegrid_available() ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'ProfileGrid Custom Tabs requires ProfileGrid to be installed and active.', 'cc-profilegrid-custom-tabs' ) . '</p></div>';
    }

    public function plugin_action_links( $links ) {
        if ( current_user_can( $this->capability() ) ) {
            array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) . '">' . esc_html__( 'Settings', 'cc-profilegrid-custom-tabs' ) . '</a>' );
        }
        return $links;
    }

    private function capability() {
        return (string) apply_filters( 'ccpgt_manage_capability', 'manage_options' );
    }

    public function admin_menu() {
        if ( ! $this->profilegrid_available() ) {
            return;
        }

        add_submenu_page(
            '',
            __( 'Custom Profile Tabs', 'cc-profilegrid-custom-tabs' ),
            __( 'Custom Tabs', 'cc-profilegrid-custom-tabs' ),
            $this->capability(),
            self::PAGE_SLUG,
            array( $this, 'settings_page' )
        );
    }

    public function admin_assets( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( self::PAGE_SLUG !== $page ) {
            return;
        }

        wp_enqueue_style( 'pm-font-awesome' );
        $admin_css_path = CCPGT_DIR . 'assets/admin.css';
        $admin_js_path  = CCPGT_DIR . 'assets/admin.js';

        wp_enqueue_style(
            'ccpgt-admin',
            CCPGT_URL . 'assets/admin.css',
            array( 'pm-font-awesome' ),
            file_exists( $admin_css_path ) ? filemtime( $admin_css_path ) : null
        );

        wp_enqueue_editor();

        wp_enqueue_script(
            'ccpgt-admin',
            CCPGT_URL . 'assets/admin.js',
            array( 'jquery', 'editor' ),
            file_exists( $admin_js_path ) ? filemtime( $admin_js_path ) : null,
            true
        );
    }

    public function render_settings_tile() {
        if ( ! current_user_can( $this->capability() ) ) {
            return;
        }

        $url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        ?>
        <div class="uimrow">
            <a href="<?php echo esc_url( $url ); ?>">
                <div class="pm_setting_image">
                    <img src="<?php echo esc_url( CCPGT_URL . 'assets/custom-tabs.svg' ); ?>" class="options" alt="<?php esc_attr_e( 'Custom Profile Tabs', 'cc-profilegrid-custom-tabs' ); ?>">
                </div>
                <div class="pm-setting-heading">
                    <span class="pm-setting-icon-title"><?php esc_html_e( 'Custom Profile Tabs', 'cc-profilegrid-custom-tabs' ); ?></span>
                    <span class="pm-setting-description"><?php esc_html_e( 'Add custom tabs, content and shortcodes to profiles.', 'cc-profilegrid-custom-tabs' ); ?></span>
                </div>
            </a>
        </div>
        <?php
    }

    public function settings_page() {
        if ( ! current_user_can( $this->capability() ) ) {
            wp_die( esc_html__( 'You do not have permission to manage these settings.', 'cc-profilegrid-custom-tabs' ) );
        }

        if ( isset( $_POST['ccpgt_reorder'] ) ) {
            check_admin_referer( 'ccpgt_save_tabs', 'ccpgt_nonce' );
            wp_safe_redirect( admin_url( 'admin.php?page=pm_profile_tabs_settings' ) );
            exit;
        }

        if ( isset( $_POST['ccpgt_save'] ) ) {
            check_admin_referer( 'ccpgt_save_tabs', 'ccpgt_nonce' );
            $raw = isset( $_POST['ccpgt_tabs'] ) && is_array( $_POST['ccpgt_tabs'] ) ? wp_unslash( $_POST['ccpgt_tabs'] ) : array();
            update_option( self::OPTION_KEY, $this->sanitize_tabs( $raw ), false );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Custom tabs saved.', 'cc-profilegrid-custom-tabs' ) . '</p></div>';
        }

        $tabs   = $this->get_tabs();
        $groups       = $this->get_groups();
        $group_fields = $this->get_group_fields();
        $roles        = $this->get_roles();
        $icons        = $this->font_awesome_icons();
        ?>
        <div class="uimagic ccpgt-wrap">
            <form method="post">
                <div class="content">
                    <div class="uimheader"><?php esc_html_e( 'Custom Profile Tabs', 'cc-profilegrid-custom-tabs' ); ?></div>
                    <div class="uimsubheader"><?php esc_html_e( 'Create and manage custom profile tabs. Final tab order is controlled from Profile Tabs Settings.', 'cc-profilegrid-custom-tabs' ); ?></div>

                    <?php wp_nonce_field( 'ccpgt_save_tabs', 'ccpgt_nonce' ); ?>

                    <div id="ccpgt-tabs" class="pg-profile-tabs-wrap">
                        <?php foreach ( $tabs as $index => $tab ) : ?>
                            <?php $this->render_admin_row( $index, $tab, $groups, $group_fields, $roles, $icons ); ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="buttonarea">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=pm_settings' ) ); ?>">
                            <div class="cancel">&#8592; &nbsp;<?php esc_html_e( 'Cancel', 'cc-profilegrid-custom-tabs' ); ?></div>
                        </a>
                        <input type="submit" value="<?php esc_attr_e( 'Save', 'cc-profilegrid-custom-tabs' ); ?>" name="ccpgt_save" id="ccpgt_save">
                        <input type="submit" value="<?php esc_attr_e( 'Add Custom Tab', 'cc-profilegrid-custom-tabs' ); ?>" id="ccpgt-add-tab">
                        <input type="submit" value="<?php esc_attr_e( 'Reorder Tabs', 'cc-profilegrid-custom-tabs' ); ?>" name="ccpgt_reorder" id="ccpgt_reorder">
                    </div>
                </div>
            </form>

            <script type="text/html" id="tmpl-ccpgt-row">
                <?php $this->render_admin_row( '__INDEX__', $this->blank_tab(), $groups, $group_fields, $roles, $icons ); ?>
            </script>
        </div>
        <?php
    }

    private function render_admin_row( $index, $tab, $groups, $group_fields, $roles, $icons ) {
        $visibility      = isset( $tab['visibility'] ) ? $tab['visibility'] : 'everyone';
        $selected_groups = isset( $tab['groups'] ) && is_array( $tab['groups'] ) ? array_map( 'intval', $tab['groups'] ) : array();
        $selected_roles  = isset( $tab['roles'] ) && is_array( $tab['roles'] ) ? array_map( 'sanitize_key', $tab['roles'] ) : array();
        $content_source  = isset( $tab['content_source'] ) ? sanitize_key( $tab['content_source'] ) : 'custom';
        $source_group    = isset( $tab['source_group'] ) ? absint( $tab['source_group'] ) : 0;
        $source_fields   = isset( $tab['source_fields'] ) && is_array( $tab['source_fields'] ) ? array_map( 'absint', $tab['source_fields'] ) : array();
        $hide_from_about = ! empty( $tab['hide_from_about'] );
        ?>
        <section class="ccpgt-tab-card is-collapsed" data-index="<?php echo esc_attr( $index ); ?>">
            <div class="pm-custom-field-page-slab pg_profile_tab ccpgt-card-header">
                <div class="pm-slab-info">
                    <span class="ccpgt-title-icon"><?php if ( ! empty( $tab['icon'] ) ) : ?><i class="<?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></i><?php endif; ?></span>
                    <span class="ccpgt-title-preview"><?php echo esc_html( $tab['title'] ?: __( 'New Tab', 'cc-profilegrid-custom-tabs' ) ); ?></span>
                </div>
                <div class="pm-slab-buttons">
                    <button type="button" class="button-link ccpgt-toggle" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
                        <span class="screen-reader-text"><?php esc_html_e( 'Collapse or expand tab settings', 'cc-profilegrid-custom-tabs' ); ?></span>
                    </button>
                    <button type="button" class="button-link-delete ccpgt-remove"><?php esc_html_e( 'Remove', 'cc-profilegrid-custom-tabs' ); ?></button>
                </div>
            </div>

            <div class="pg_profile_tab-setting ccpgt-card-body" style="display:none;">
                <div class="uimrow">
                    <div class="uimfield"><label><?php esc_html_e( 'Title', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <input type="text" class="ccpgt-title" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $tab['title'] ); ?>" required>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Profile tab title.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><label><?php esc_html_e( 'Slug', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <input type="text" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $tab['slug'] ); ?>" placeholder="messages">
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Unique internal slug for this tab.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><?php esc_html_e( 'Icon', 'cc-profilegrid-custom-tabs' ); ?></div>
                    <div class="uiminput">
                        <div class="ccpgt-icon-field">
                            <input type="hidden" class="ccpgt-icon-value" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][icon]" value="<?php echo esc_attr( $tab['icon'] ); ?>">
                            <button type="button" class="button ccpgt-icon-trigger">
                                <span class="ccpgt-icon-preview">
                                    <?php if ( ! empty( $tab['icon'] ) ) : ?>
                                        <i class="<?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></i>
                                    <?php else : ?>
                                        <i class="fa fa-plus-square-o" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="ccpgt-icon-label"><?php echo ! empty( $tab['icon'] ) ? esc_html__( 'Change icon', 'cc-profilegrid-custom-tabs' ) : esc_html__( 'Choose icon', 'cc-profilegrid-custom-tabs' ); ?></span>
                            </button>
                            <div class="ccpgt-icon-picker" hidden>
                                <div class="ccpgt-icon-picker-toolbar">
                                    <input type="search" class="ccpgt-icon-search" placeholder="<?php esc_attr_e( 'Search icons...', 'cc-profilegrid-custom-tabs' ); ?>">
                                    <button type="button" class="button-link ccpgt-icon-clear"><?php esc_html_e( 'No icon', 'cc-profilegrid-custom-tabs' ); ?></button>
                                </div>
                                <div class="ccpgt-icon-grid" role="listbox" aria-label="<?php esc_attr_e( 'Font Awesome icons', 'cc-profilegrid-custom-tabs' ); ?>">
                                    <?php foreach ( $icons as $icon_class => $icon_label ) : ?>
                                        <button type="button" class="ccpgt-icon-option<?php echo $tab['icon'] === $icon_class ? ' is-selected' : ''; ?>" data-icon="<?php echo esc_attr( $icon_class ); ?>" data-search="<?php echo esc_attr( strtolower( $icon_label . ' ' . $icon_class ) ); ?>" title="<?php echo esc_attr( $icon_label ); ?>" aria-label="<?php echo esc_attr( $icon_label ); ?>"><i class="<?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></i></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Optional Font Awesome icon.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><label><?php esc_html_e( 'Visibility', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <select name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][visibility]">
                            <option value="everyone" <?php selected( $visibility, 'everyone' ); ?>><?php esc_html_e( 'Everyone', 'cc-profilegrid-custom-tabs' ); ?></option>
                            <option value="logged_in" <?php selected( $visibility, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users', 'cc-profilegrid-custom-tabs' ); ?></option>
                            <option value="owner" <?php selected( $visibility, 'owner' ); ?>><?php esc_html_e( 'Profile owner only', 'cc-profilegrid-custom-tabs' ); ?></option>
                        </select>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Who can view this tab.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><?php esc_html_e( 'Enabled', 'cc-profilegrid-custom-tabs' ); ?></div>
                    <div class="uiminput">
                        <input type="checkbox" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $tab['enabled'] ) ); ?>>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Enable or disable this custom tab.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><label><?php esc_html_e( 'ProfileGrid Groups', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <select multiple class="ccpgt-multiselect" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][groups][]">
                            <?php foreach ( $groups as $group_id => $group_name ) : ?>
                                <option value="<?php echo esc_attr( $group_id ); ?>" <?php selected( in_array( (int) $group_id, $selected_groups, true ) ); ?>><?php echo esc_html( $group_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link ccpgt-clear-multiselect"><?php esc_html_e( 'Clear selection (all groups)', 'cc-profilegrid-custom-tabs' ); ?></button>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Leave empty to allow profiles from any group.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><label><?php esc_html_e( 'WordPress Roles', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <select multiple class="ccpgt-multiselect" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][roles][]">
                            <?php foreach ( $roles as $role_key => $role_name ) : ?>
                                <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( in_array( (string) $role_key, $selected_roles, true ) ); ?>><?php echo esc_html( $role_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link ccpgt-clear-multiselect"><?php esc_html_e( 'Clear selection (all roles)', 'cc-profilegrid-custom-tabs' ); ?></button>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Leave empty to allow all roles that otherwise meet the visibility rules.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow">
                    <div class="uimfield"><label><?php esc_html_e( 'Content Source', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <select class="ccpgt-content-source" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][content_source]">
                            <option value="custom" <?php selected( $content_source, 'custom' ); ?>><?php esc_html_e( 'Custom Content / Shortcodes', 'cc-profilegrid-custom-tabs' ); ?></option>
                            <option value="profilegrid_fields" <?php selected( $content_source, 'profilegrid_fields' ); ?>><?php esc_html_e( 'ProfileGrid Fields', 'cc-profilegrid-custom-tabs' ); ?></option>
                        </select>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Choose whether this tab uses custom content or fields saved in ProfileGrid user profiles.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow ccpgt-pg-fields-row"<?php echo 'profilegrid_fields' === $content_source ? '' : ' style="display:none;"'; ?>>
                    <div class="uimfield"><label><?php esc_html_e( 'Source Group', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <select class="ccpgt-source-group" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][source_group]">
                            <option value=""><?php esc_html_e( 'Select a group', 'cc-profilegrid-custom-tabs' ); ?></option>
                            <?php foreach ( $groups as $group_id => $group_name ) : ?>
                                <option value="<?php echo esc_attr( $group_id ); ?>" <?php selected( $source_group, (int) $group_id ); ?>><?php echo esc_html( $group_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Choose the ProfileGrid group whose fields should be available in this tab.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow ccpgt-pg-fields-row"<?php echo 'profilegrid_fields' === $content_source ? '' : ' style="display:none;"'; ?>>
                    <div class="uimfield"><label><?php esc_html_e( 'ProfileGrid Fields', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <select multiple class="ccpgt-source-fields ccpgt-multiselect" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][source_fields][]">
                            <?php foreach ( $group_fields as $field_group_id => $fields ) : ?>
                                <?php foreach ( $fields as $field_id => $field_label ) : ?>
                                    <option value="<?php echo esc_attr( $field_id ); ?>" data-group="<?php echo esc_attr( $field_group_id ); ?>" <?php selected( in_array( (int) $field_id, $source_fields, true ) ); ?>><?php echo esc_html( $field_label ); ?></option>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link ccpgt-clear-multiselect"><?php esc_html_e( 'Clear selected fields', 'cc-profilegrid-custom-tabs' ); ?></button>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Select one or more fields to display in this tab. Values come from the profile being viewed.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow ccpgt-pg-fields-row"<?php echo 'profilegrid_fields' === $content_source ? '' : ' style="display:none;"'; ?>>
                    <div class="uimfield"><?php esc_html_e( 'Hide from About', 'cc-profilegrid-custom-tabs' ); ?></div>
                    <div class="uiminput">
                        <input type="checkbox" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][hide_from_about]" value="1" <?php checked( $hide_from_about ); ?>>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Hide the selected ProfileGrid fields from the standard About section and show them only in this custom tab.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>

                <div class="uimrow ccpgt-editor-row ccpgt-custom-content-row"<?php echo 'custom' === $content_source ? '' : ' style="display:none;"'; ?>>
                    <div class="uimfield"><label><?php esc_html_e( 'Content / Shortcodes', 'cc-profilegrid-custom-tabs' ); ?></label></div>
                    <div class="uiminput">
                        <textarea id="ccpgt-content-<?php echo esc_attr( $index ); ?>" class="ccpgt-editor" rows="10" name="ccpgt_tabs[<?php echo esc_attr( $index ); ?>][content]" placeholder="[your_shortcode]"><?php echo esc_textarea( $tab['content'] ); ?></textarea>
                    </div>
                    <div class="uimnote"><?php esc_html_e( 'Text, HTML, and WordPress shortcodes are supported.', 'cc-profilegrid-custom-tabs' ); ?></div>
                </div>
            </div>
        </section>
        <?php
    }

    private function blank_tab() {
        return array(
            'title'      => '',
            'slug'       => '',
            'icon'       => '',
            'visibility' => 'everyone',
            'groups'     => array(),
            'roles'      => array(),
            'content_source' => 'custom',
            'source_group'   => 0,
            'source_fields'    => array(),
            'hide_from_about' => 0,
            'content'          => '',
            'enabled'        => 1,
        );
    }

    private function sanitize_tabs( $raw ) {
        $tabs = array();
        $used = array();

        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $title = sanitize_text_field( $row['title'] ?? '' );
            if ( '' === $title ) {
                continue;
            }

            $slug = sanitize_title( $row['slug'] ?? $title );
            if ( '' === $slug ) {
                $slug = 'tab';
            }

            $base = $slug;
            $n    = 2;
            while ( isset( $used[ $slug ] ) ) {
                $slug = $base . '-' . $n;
                ++$n;
            }
            $used[ $slug ] = true;

            $visibility = sanitize_key( $row['visibility'] ?? 'everyone' );
            if ( ! in_array( $visibility, array( 'everyone', 'logged_in', 'owner' ), true ) ) {
                $visibility = 'everyone';
            }

            $groups = isset( $row['groups'] ) && is_array( $row['groups'] )
                ? array_values( array_unique( array_filter( array_map( 'absint', $row['groups'] ) ) ) )
                : array();

            $roles = isset( $row['roles'] ) && is_array( $row['roles'] )
                ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $row['roles'] ) ) ) )
                : array();

            $content_source = sanitize_key( $row['content_source'] ?? 'custom' );
            if ( ! in_array( $content_source, array( 'custom', 'profilegrid_fields' ), true ) ) {
                $content_source = 'custom';
            }

            $source_group = absint( $row['source_group'] ?? 0 );
            $source_fields = isset( $row['source_fields'] ) && is_array( $row['source_fields'] )
                ? array_values( array_unique( array_filter( array_map( 'absint', $row['source_fields'] ) ) ) )
                : array();

            $tabs[] = array(
                'title'      => $title,
                'slug'       => $slug,
                'icon'       => $this->sanitize_class_list( $row['icon'] ?? '' ),
                'visibility' => $visibility,
                'groups'     => $groups,
                'roles'          => $roles,
                'content_source' => $content_source,
                'source_group'   => $source_group,
                'source_fields'    => $source_fields,
                'hide_from_about' => ! empty( $row['hide_from_about'] ) ? 1 : 0,
                'content'          => wp_kses_post( $row['content'] ?? '' ),
                'enabled'    => ! empty( $row['enabled'] ) ? 1 : 0,
            );
        }

        return $tabs;
    }

    private function sanitize_class_list( $classes ) {
        $classes = preg_split( '/\s+/', trim( (string) $classes ) );
        $classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
        return implode( ' ', array_unique( $classes ) );
    }

    private function get_tabs() {
        $tabs = get_option( self::OPTION_KEY, array() );
        return is_array( $tabs ) ? $tabs : array();
    }

    private function font_awesome_icons() {
        $names = array(
            'address-book','address-card','adjust','archive','area-chart','arrows','bars','bell','bell-o','birthday-cake',
            'bolt','bookmark','book','briefcase','bullhorn','calendar','calendar-check-o','calendar-o','camera','camera-retro',
            'car','check','check-circle','check-square','circle','clock-o','cloud','cloud-download','cloud-upload','code',
            'coffee','cog','cogs','comment','comment-o','comments','comments-o','compass','credit-card','desktop','download',
            'edit','envelope','envelope-o','exclamation-circle','exclamation-triangle','external-link','eye','eye-slash','facebook',
            'file','file-audio-o','file-image-o','file-movie-o','file-o','file-pdf-o','file-text','film','filter','flag',
            'folder','folder-open','gamepad','gift','globe','google','graduation-cap','group','headphones','heart','heart-o',
            'home','image','info-circle','instagram','key','laptop','lightbulb-o','link','linkedin','list','list-alt',
            'location-arrow','lock','magic','map','map-marker','microphone','microphone-slash','mobile','music','paper-plane',
            'pencil','phone','photo','picture-o','play','play-circle','plus','plus-circle','podcast','print','question-circle',
            'quote-left','quote-right','refresh','rss','search','share','shopping-cart','sign-in','sign-out','sliders','star',
            'star-o','tag','tags','tasks','thumbs-up','ticket','times','times-circle','trash','trophy','twitter','unlock',
            'upload','user','user-circle','user-circle-o','user-plus','users','video-camera','volume-up','warning','wifi',
            'wordpress','wrench','youtube','youtube-play'
        );

        $icons = array();
        foreach ( $names as $name ) {
            $icons[ 'fa fa-' . $name ] = ucwords( str_replace( '-', ' ', $name ) );
        }

        return (array) apply_filters( 'ccpgt_font_awesome_icons', $icons );
    }

    private function get_roles() {
        $roles = array();

        if ( ! function_exists( 'wp_roles' ) ) {
            return $roles;
        }

        $wp_roles = wp_roles();
        if ( ! $wp_roles || ! is_array( $wp_roles->roles ) ) {
            return $roles;
        }

        foreach ( $wp_roles->roles as $role_key => $role_data ) {
            $roles[ (string) $role_key ] = translate_user_role( $role_data['name'] );
        }

        return $roles;
    }

    private function get_groups() {
        $groups = array();

        if ( ! class_exists( 'PM_DBhandler' ) ) {
            return $groups;
        }

        $dbhandler = new PM_DBhandler();
        $rows      = $dbhandler->get_all_result( 'GROUPS' );

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( isset( $row->id, $row->group_name ) ) {
                    $groups[ (int) $row->id ] = (string) $row->group_name;
                }
            }
        }

        return $groups;
    }

    private function get_group_fields() {
        $result = array();

        if ( ! class_exists( 'PM_DBhandler' ) ) {
            return $result;
        }

        $dbhandler = new PM_DBhandler();
        $excluded_types = array( 'user_name', 'user_avatar', 'user_pass', 'confirm_pass', 'heading', 'paragraph' );

        foreach ( $this->get_groups() as $group_id => $group_name ) {
            $rows = $dbhandler->get_all_result(
                'FIELDS',
                '*',
                array( 'associate_group' => (int) $group_id ),
                'results',
                0,
                false,
                'ordering'
            );

            if ( ! is_array( $rows ) ) {
                continue;
            }

            foreach ( $rows as $field ) {
                if (
                    ! isset( $field->field_id, $field->field_name, $field->field_type ) ||
                    in_array( (string) $field->field_type, $excluded_types, true )
                ) {
                    continue;
                }

                $result[ (int) $group_id ][ (int) $field->field_id ] = (string) $field->field_name;
            }
        }

        return $result;
    }

    private function get_selected_profilegrid_fields( $group_id, $field_ids ) {
        if ( ! class_exists( 'PM_DBhandler' ) || ! $group_id || ! is_array( $field_ids ) || ! $field_ids ) {
            return array();
        }

        $dbhandler = new PM_DBhandler();
        $rows = $dbhandler->get_all_result(
            'FIELDS',
            '*',
            array( 'associate_group' => (int) $group_id ),
            'results',
            0,
            false,
            'ordering'
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        $wanted = array_map( 'absint', $field_ids );
        $fields = array();

        foreach ( $rows as $field ) {
            if ( isset( $field->field_id ) && in_array( (int) $field->field_id, $wanted, true ) ) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function register_tabs( $tabs ) {
        if ( ! is_array( $tabs ) ) {
            $tabs = array();
        }

        foreach ( $this->get_tabs() as $custom ) {
            $id = 'ccpgt-' . sanitize_title( $custom['slug'] );

            if ( empty( $custom['enabled'] ) ) {
                unset( $tabs[ $id ] );
                continue;
            }

            $saved_status = isset( $tabs[ $id ]['status'] ) ? (string) $tabs[ $id ]['status'] : '1';

            $tabs[ $id ] = array(
                'id'               => $id,
                'title'            => $custom['title'],
                'status'           => '1' === $saved_status ? '1' : '0',
                'class'            => 'ccpgt-profile-tab',
                'ccpgt_visibility' => $custom['visibility'],
                'ccpgt_groups'     => $custom['groups'],
                'ccpgt_roles'      => $custom['roles'],
                'ccpgt_icon'           => $custom['icon'],
                'ccpgt_content_source' => $custom['content_source'] ?? 'custom',
                'ccpgt_source_group'   => $custom['source_group'] ?? 0,
                'ccpgt_source_fields'  => $custom['source_fields'] ?? array(),
                'ccpgt_hide_from_about' => ! empty( $custom['hide_from_about'] ) ? 1 : 0,
                'ccpgt_content'         => $custom['content'],
            );
        }

        return $tabs;
    }

    private function tab_is_visible( $tab, $uid, $gid ) {
        $visibility = $tab['ccpgt_visibility'] ?? 'everyone';
        $current_id = get_current_user_id();

        if ( 'logged_in' === $visibility && ! is_user_logged_in() ) {
            return false;
        }

        if ( 'owner' === $visibility && (int) $uid !== (int) $current_id ) {
            return false;
        }

        $required_roles = isset( $tab['ccpgt_roles'] ) && is_array( $tab['ccpgt_roles'] )
            ? array_map( 'sanitize_key', $tab['ccpgt_roles'] )
            : array();

        if ( $required_roles ) {
            if ( ! $current_id ) {
                return false;
            }

            $current_user = wp_get_current_user();
            $current_roles = is_array( $current_user->roles ) ? array_map( 'sanitize_key', $current_user->roles ) : array();

            if ( ! array_intersect( $required_roles, $current_roles ) ) {
                return false;
            }
        }

        $required_groups = isset( $tab['ccpgt_groups'] ) && is_array( $tab['ccpgt_groups'] )
            ? array_map( 'intval', $tab['ccpgt_groups'] )
            : array();

        if ( $required_groups ) {
            $profile_groups = is_array( $gid ) ? array_map( 'intval', $gid ) : array( (int) $gid );

            if ( ! array_intersect( $required_groups, $profile_groups ) ) {
                return false;
            }
        }

        return (bool) apply_filters( 'ccpgt_tab_visible', true, $tab, (int) $uid, $gid );
    }

    public function exclude_custom_tab_fields_from_about( $additional, $uid, $gid, $group_leader, $view, $section, $exclude ) {
        if ( 'group' === $view ) {
            return $additional;
        }

        $field_ids = array();

        foreach ( $this->get_tabs() as $tab ) {
            if (
                empty( $tab['enabled'] ) ||
                empty( $tab['hide_from_about'] ) ||
                ( $tab['content_source'] ?? 'custom' ) !== 'profilegrid_fields'
            ) {
                continue;
            }

            $source_group  = absint( $tab['source_group'] ?? 0 );
            $source_fields = isset( $tab['source_fields'] ) && is_array( $tab['source_fields'] )
                ? array_values( array_unique( array_filter( array_map( 'absint', $tab['source_fields'] ) ) ) )
                : array();

            if ( ! $source_group || ! $source_fields ) {
                continue;
            }

            foreach ( $this->get_selected_profilegrid_fields( $source_group, $source_fields ) as $field ) {
                if ( isset( $field->field_id ) ) {
                    $field_ids[] = absint( $field->field_id );
                }
            }
        }

        $field_ids = array_values( array_unique( array_filter( $field_ids ) ) );

        if ( $field_ids ) {
            $additional .= ' AND field_id NOT IN (' . implode( ',', $field_ids ) . ')';
        }

        return $additional;
    }

    public function render_tab_link( $id, $tab, $uid, $gid, $primary_gid ) {
        if ( 0 !== strpos( (string) $id, 'ccpgt-' ) || ! $this->tab_is_visible( $tab, $uid, $gid ) ) {
            return;
        }

        $icon = isset( $tab['ccpgt_icon'] ) ? trim( (string) $tab['ccpgt_icon'] ) : '';

        echo '<li class="pm-profile-tab pm-pad10 ccpgt-profile-tab">';
        echo '<a class="pm-dbfl" href="#' . esc_attr( $id ) . '">';

        if ( '' !== $icon ) {
            echo '<i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i> ';
        }

        echo esc_html( $tab['title'] );
        echo '</a></li>';
    }

    public function render_tab_content( $id, $tab, $uid, $gid, $primary_gid ) {
        if ( 0 !== strpos( (string) $id, 'ccpgt-' ) || ! $this->tab_is_visible( $tab, $uid, $gid ) ) {
            return;
        }

        $content_source = isset( $tab['ccpgt_content_source'] ) ? sanitize_key( $tab['ccpgt_content_source'] ) : 'custom';

        echo '<div id="' . esc_attr( $id ) . '" class="pm-dbfl pg_custom_tab_content ccpgt-tab-content">';
        echo '<div class="pm-section pm-dbfl"><div class="pm-section-content pm-dbfl">';

        if ( 'profilegrid_fields' === $content_source ) {
            $source_group  = isset( $tab['ccpgt_source_group'] ) ? absint( $tab['ccpgt_source_group'] ) : 0;
            $source_fields = isset( $tab['ccpgt_source_fields'] ) && is_array( $tab['ccpgt_source_fields'] )
                ? array_map( 'absint', $tab['ccpgt_source_fields'] )
                : array();
            $fields = $this->get_selected_profilegrid_fields( $source_group, $source_fields );

            if ( $fields && class_exists( 'PM_HTML_Creator' ) ) {
                $creator = new PM_HTML_Creator( 'profilegrid-user-profiles-groups-and-communities', defined( 'PROFILE_MAGIC_VERSION' ) ? PROFILE_MAGIC_VERSION : '' );
                $creator->get_user_meta_fields_html( $fields, (int) $uid );
            }
        } else {
            $content = isset( $tab['ccpgt_content'] ) ? (string) $tab['ccpgt_content'] : '';
            $content = apply_filters( 'ccpgt_tab_content', $content, $tab, (int) $uid, $gid, $primary_gid );
            echo do_shortcode( wpautop( wp_kses_post( $content ) ) );
        }

        echo '</div></div></div>';
    }
}
