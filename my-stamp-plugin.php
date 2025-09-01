<?php
/*
Plugin Name: My Stamp Plugin
Description: Personnalisation de tampons, cartes digitales avec AR, analytics, leads, chat, et intégration WooCommerce.
Version: 2.6.2
Author: You
Text Domain: my-stamp-plugin
*/

if (!defined('ABSPATH')) { exit; }

/* Helpers */
function msp_assets_url($rel = '') {
	$url = plugins_url('assets/' . ltrim($rel, '/'), __FILE__);
	return $url;
}
function msp_assets_path($rel = '') {
	return plugin_dir_path(__FILE__) . 'assets/' . ltrim($rel, '/');
}
function msp_get_page_url_by_slug($slug) {
	$page = get_page_by_path($slug);
	if ($page) return get_permalink($page->ID);
	return home_url('/' . trim($slug, '/') . '/');
}
/* Themes */
function msp_valid_themes() {
	return [
		'flag' => 'Drapeau France',
		'luxe' => 'Luxe Navy/Or', 
		'min' => 'Bleu Minimal',
		'classique' => 'Classique',
		'verre' => 'Verre',
		'ombre' => 'Ombre',
		'neon' => 'Néon',
		'carte' => 'Carte',
		'moderne' => 'Moderne',
		'degrade' => 'Dégradé',
		'sombre' => 'Sombre',
		'clair' => 'Clair',
		'corporate' => 'Corporate',
		'pastel' => 'Pastel'
	];
}

/* Create pages and database tables on activation */
register_activation_hook(__FILE__, function () {
	$pages = [
		'stamp-customizer' => ['title' => 'Stamp Customizer', 'content' => '[stamp_customizer_iframe]'],
		'digital-card' => ['title' => 'Digital Card', 'content' => '[digital_card_iframe][dc_user_form]'],
		'login' => ['title' => 'Connexion', 'content' => '[msp_login_form]'],
		'ar-builder' => ['title' => 'Constructeur AR', 'content' => '[msp_ar_builder]'],
		'dashboard' => ['title' => 'Tableau de bord', 'content' => '[msp_user_dashboard]'],
		'ar-form' => ['title' => 'Formulaire AR', 'content' => '[msp_ar_form]'],
		'card-preview' => ['title' => 'Aperçu de la carte', 'content' => '[msp_card_preview]'],
		'card-view' => ['title' => 'Ma carte', 'content' => '[msp_view_card]'],
		'chat' => ['title' => 'Chat', 'content' => '[msp_chat]'],
		'register' => ['title' => 'Créer un compte', 'content' => '[msp_register_form]']
	];
	foreach ($pages as $slug => $page_data) {
		$existing_page = get_page_by_path($slug);
		if (!$existing_page) {
			wp_insert_post([
				'post_title' => $page_data['title'],
				'post_name' => $slug,
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_content' => $page_data['content']
			]);
		}
	}

	global $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$charset = $wpdb->get_charset_collate();

	$analytics_table = $wpdb->prefix . 'digital_card_analytics';
	dbDelta("CREATE TABLE $analytics_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		card_id mediumint(9) NOT NULL,
		user_id mediumint(9) NOT NULL,
		action_type varchar(50) NOT NULL,
		action_data text,
		ip_address varchar(45),
		user_agent text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY card_id (card_id),
		KEY user_id (user_id),
		KEY action_type (action_type)
	) $charset;");

	$leads_table = $wpdb->prefix . 'digital_card_leads';
	dbDelta("CREATE TABLE $leads_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		card_id mediumint(9) NOT NULL,
		user_id mediumint(9) NOT NULL,
		lead_name varchar(255) NOT NULL,
		lead_email varchar(255),
		lead_phone varchar(50),
		lead_company varchar(255),
		lead_message text,
		ip_address varchar(45),
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY card_id (card_id),
		KEY user_id (user_id)
	) $charset;");

	$company_table = $wpdb->prefix . 'digital_card_companies';
	dbDelta("CREATE TABLE $company_table (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		user_id mediumint(9) NOT NULL,
		company_name varchar(255) NOT NULL,
		company_logo varchar(500),
		company_address text,
		company_phone varchar(50),
		company_email varchar(255),
		company_website varchar(255),
		company_social text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id)
	) $charset;");

	$threads = $wpdb->prefix.'msp_chat_threads';
	$messages= $wpdb->prefix.'msp_chat_messages';
	dbDelta("CREATE TABLE $threads (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT UNSIGNED NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id), KEY user_id (user_id)
	) $charset;");
	dbDelta("CREATE TABLE $messages (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		thread_id BIGINT UNSIGNED NOT NULL,
		sender varchar(10) NOT NULL,
		message longtext NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id), KEY thread_id (thread_id)
	) $charset;");

	flush_rewrite_rules();
});

/* Ensure required pages exist (if plugin updated after install) */
add_action('admin_init', function () {
	$pages = [
		'ar-form' => ['title' => 'Formulaire AR', 'content' => '[msp_ar_form]'],
		'card-preview' => ['title' => 'Aperçu de la carte', 'content' => '[msp_card_preview]'],
		'card-view' => ['title' => 'Ma carte', 'content' => '[msp_view_card]'],
	];
	foreach ($pages as $slug => $data) {
		if (!get_page_by_path($slug)) {
			wp_insert_post([
				'post_title' => $data['title'],
				'post_name' => $slug,
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_content' => $data['content']
			]);
		}
	}
});

/* Settings: reCAPTCHA + email verification + Global pricing */
add_action('admin_menu', function(){
	add_menu_page('MSP Dashboard','MSP Dashboard','manage_options','msp-dashboard','msp_admin_dashboard_page','dashicons-chart-area',30);
	add_submenu_page('msp-dashboard','Sécurité (reCAPTCHA + Email)','Sécurité','manage_options','msp-security','msp_admin_security_page');
	add_submenu_page('msp-dashboard','Tarification Globale','Tarification','manage_options','msp-pricing','msp_admin_pricing_page');
});
function msp_admin_security_page(){
	if (!current_user_can('manage_options')) return;
	if (isset($_POST['msp_save_security']) && check_admin_referer('msp_sec_opts')){
		update_option('msp_recaptcha_site', sanitize_text_field($_POST['msp_recaptcha_site'] ?? ''));
		update_option('msp_recaptcha_secret', sanitize_text_field($_POST['msp_recaptcha_secret'] ?? ''));
		update_option('msp_email_verify_required', isset($_POST['msp_email_verify_required']) ? '1' : '0');
		echo '<div class="updated"><p>Enregistré.</p></div>';
	}
	$site = esc_attr(get_option('msp_recaptcha_site',''));
	$secret = esc_attr(get_option('msp_recaptcha_secret',''));
	$req = get_option('msp_email_verify_required','1')==='1';
	echo '<div class="wrap"><h1>Sécurité</h1><form method="post">';
	wp_nonce_field('msp_sec_opts');
	echo '<table class="form-table">
<tr><th>reCAPTCHA site key</th><td><input type="text" name="msp_recaptcha_site" value="'.$site.'" class="regular-text"></td></tr>
<tr><th>reCAPTCHA secret</th><td><input type="text" name="msp_recaptcha_secret" value="'.$secret.'" class="regular-text"></td></tr>
<tr><th>Exiger vérification email</th><td><label><input type="checkbox" name="msp_email_verify_required" '.($req?'checked':'').'> Oui</label></td></tr>
</table>
<p><button class="button button-primary" name="msp_save_security" value="1">Enregistrer</button></p>
</form></div>';
}
function msp_admin_pricing_page(){
	if (!current_user_can('manage_options')) return;
	if (isset($_POST['msp_save_pricing']) && check_admin_referer('msp_price_opts')){
		update_option('msp_dc_price_bois', sanitize_text_field($_POST['msp_dc_price_bois'] ?? '0'));
		update_option('msp_dc_price_metal', sanitize_text_field($_POST['msp_dc_price_metal'] ?? '0'));
		update_option('msp_dc_price_pvc', sanitize_text_field($_POST['msp_dc_price_pvc'] ?? '0'));

		$ink_lines   = array_map('trim', explode("\n", str_replace("\r",'', (string)($_POST['msp_stamp_price_ink_map'] ?? ''))));
		$shape_lines = array_map('trim', explode("\n", str_replace("\r",'', (string)($_POST['msp_stamp_price_shape_map'] ?? ''))));
		$size_lines  = array_map('trim', explode("\n", str_replace("\r",'', (string)($_POST['msp_stamp_price_size_map'] ?? ''))));

		update_option('msp_stamp_price_ink_map', wp_json_encode($ink_lines));
		update_option('msp_stamp_price_shape_map', wp_json_encode($shape_lines));
		update_option('msp_stamp_price_size_map', wp_json_encode($size_lines));

		echo '<div class="updated"><p>Tarifs enregistrés.</p></div>';
	}
	$bois = esc_attr(get_option('msp_dc_price_bois','0'));
	$metal = esc_attr(get_option('msp_dc_price_metal','0'));
	$pvc = esc_attr(get_option('msp_dc_price_pvc','0'));
	$ink_map = json_decode(get_option('msp_stamp_price_ink_map','[]'), true);
	$shape_map = json_decode(get_option('msp_stamp_price_shape_map','[]'), true);
	$size_map = json_decode(get_option('msp_stamp_price_size_map','[]'), true);
	echo '<div class="wrap"><h1>Tarification Globale</h1><form method="post">';
	wp_nonce_field('msp_price_opts');
	echo '<h2>Carte Digitale (matériaux)</h2>
<table class="form-table">
<tr><th>Bois (€)</th><td><input name="msp_dc_price_bois" value="'.$bois.'" class="regular-text"></td></tr>
<tr><th>Métal (€)</th><td><input name="msp_dc_price_metal" value="'.$metal.'" class="regular-text"></td></tr>
<tr><th>PVC (€)</th><td><input name="msp_dc_price_pvc" value="'.$pvc.'" class="regular-text"></td></tr>
</table>
<h2>Tampon (prix par option)</h2>
<p>Format: une ligne par clé=prix. Ex: noir=1.2 | circle=2.0 | small=0.5</p>
<table class="form-table">
<tr><th>Couleurs (encre)</th><td><textarea name="msp_stamp_price_ink_map" rows="6" class="large-text">'.esc_textarea(is_array($ink_map)?implode("\n",$ink_map):'').'</textarea></td></tr>
<tr><th>Formes</th><td><textarea name="msp_stamp_price_shape_map" rows="6" class="large-text">'.esc_textarea(is_array($shape_map)?implode("\n",$shape_map):'').'</textarea></td></tr>
<tr><th>Tailles</th><td><textarea name="msp_stamp_price_size_map" rows="6" class="large-text">'.esc_textarea(is_array($size_map)?implode("\n",$size_map):'').'</textarea></td></tr>
</table>
<p><button class="button button-primary" name="msp_save_pricing" value="1">Enregistrer</button></p>
</form></div>';
}
function msp_parse_kv_lines_to_map($lines){
	$map = [];
	if (is_array($lines)) {
		foreach ($lines as $ln) {
			$ln = trim($ln);
			if (!$ln) continue;
			if (strpos($ln,'=') !== false) {
				list($k,$v) = array_map('trim', explode('=', $ln, 2));
				$map[strtolower($k)] = floatval(str_replace(',','.',$v));
			}
		}
	}
	return $map;
}

/* Auth helpers */
function msp_is_user_logged_in() { return is_user_logged_in(); }
function msp_redirect_to_login() {
	if (!msp_is_user_logged_in()) {
		$login_url = msp_get_page_url_by_slug('login');
		if ($login_url) {
			wp_redirect($login_url . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
			exit;
		}
	}
}

/* Email verification endpoint */
add_action('template_redirect', function(){
	if (isset($_GET['msp_verify']) && $_GET['msp_verify']=='1') {
		$user_id = intval($_GET['user'] ?? 0);
		$token = sanitize_text_field($_GET['token'] ?? '');
		$good = ($user_id && $token && get_user_meta($user_id,'msp_verif_token',true)===$token);
		if ($good) {
			update_user_meta($user_id,'msp_email_verified','1');
			delete_user_meta($user_id,'msp_verif_token');
			wp_safe_redirect(msp_get_page_url_by_slug('login').'?verified=1'); exit;
		}
		wp_safe_redirect(msp_get_page_url_by_slug('login').'?verified=0'); exit;
	}
});

add_action('init', function(){
	if (!empty($_POST['msp_login_nonce']) && wp_verify_nonce($_POST['msp_login_nonce'],'msp_login_nonce')) {
		$username = sanitize_text_field($_POST['msp_username'] ?? '');
		$password = $_POST['msp_password'] ?? '';
		$recaptcha = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
		$redirect_url = isset($_POST['redirect']) ? esc_url_raw($_POST['redirect']) : home_url('/dashboard/');
		if (!$username || !$password) { wp_safe_redirect( add_query_arg('error', urlencode('Tous les champs sont requis'), msp_get_page_url_by_slug('login')) ); exit; }
		if (!msp_verify_recaptcha($recaptcha)) { wp_safe_redirect( add_query_arg('error', urlencode('reCAPTCHA invalide'), msp_get_page_url_by_slug('login')) ); exit; }

		$user = wp_signon(['user_login'=>$username,'user_password'=>$password,'remember'=>true], is_ssl());
		if (is_wp_error($user)) { wp_safe_redirect( add_query_arg('error', urlencode('Identifiants incorrects'), msp_get_page_url_by_slug('login')) ); exit; }

		if (get_option('msp_email_verify_required','1')==='1' && get_user_meta($user->ID,'msp_email_verified',true)!=='1') {
			wp_logout();
			wp_safe_redirect( add_query_arg('error', urlencode('Veuillez vérifier votre email avant de vous connecter.'), msp_get_page_url_by_slug('login')) ); exit;
		}
		wp_set_auth_cookie($user->ID, true, is_ssl());
		wp_set_current_user($user->ID);
		do_action('wp_login', $user->user_login, $user);
		wp_safe_redirect($redirect_url); exit;
	}
});

/* reCAPTCHA verify */
function msp_verify_recaptcha($token){
	$secret = get_option('msp_recaptcha_secret','');
	if (!$secret) return true; // skip if not configured
	$resp = wp_remote_post("https://www.google.com/recaptcha/api/siteverify", [
		'timeout'=>10,
		'body'=>[
			'secret'=>$secret,
			'response'=>$token,
			'remoteip'=>$_SERVER['REMOTE_ADDR'] ?? ''
		]
	]);
	if (is_wp_error($resp)) return false;
	$body = json_decode(wp_remote_retrieve_body($resp), true);
	return !empty($body['success']);
}

/* Login form (AJAX, wp_signon) */
add_shortcode('msp_login_form', function () {
	if (is_user_logged_in()) {
		$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : home_url('/dashboard/');
		wp_redirect($redirect_url);
		exit;
	}
	$site_key = esc_attr(get_option('msp_recaptcha_site',''));
	$msg = '';
	if (isset($_GET['verified'])) {
		$msg = $_GET['verified']=='1' ? 'Votre email a été vérifié. Vous pouvez vous connecter.' : 'Lien de vérification invalide.';
	}
	if (isset($_GET['checkemail'])) {
		$msg = 'Un email de vérification a été envoyé. Veuillez vérifier votre boîte de réception.';
	}
	$current_redirect = isset($_GET['redirect']) ? esc_attr($_GET['redirect']) : home_url('/dashboard/');
	ob_start(); ?>
	<div style="max-width:400px;margin:50px auto;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.1);padding:32px;">
		<h2 style="margin:0 0 24px;color:#1f2937;text-align:center;">Connexion</h2>
		<?php if ($msg): ?>
		<div style="background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:12px;border-radius:8px;margin-bottom:16px;"><?php echo esc_html($msg); ?></div>
		<?php endif; ?>
		<?php if (isset($_GET['error'])): ?>
		<div style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px;border-radius:8px;margin-bottom:20px;">
			<?php echo esc_html($_GET['error']); ?>
		</div>
		<?php endif; ?>
		<form id="mspLoginForm" method="post" style="display:grid;gap:16px;">
			<?php wp_nonce_field('msp_login_nonce', 'msp_login_nonce'); ?>
			<input type="hidden" name="redirect" value="<?php echo esc_attr($current_redirect); ?>">
			<div>
				<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Nom d'utilisateur ou Email *</label>
				<input type="text" name="msp_username" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:16px;">
			</div>
			<div>
				<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Mot de passe *</label>
				<input type="password" name="msp_password" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;font-size:16px;">
			</div>
			<?php if ($site_key): ?>
			<div class="g-recaptcha" data-sitekey="<?php echo $site_key; ?>"></div>
			<script src="https://www.google.com/recaptcha/api.js" async defer></script>
			<?php endif; ?>
			<div style="text-align:center;margin-top:20px;">
				<button type="submit" style="background:#2563eb;color:#fff;border:none;padding:12px 32px;border-radius:8px;font-weight:600;cursor:pointer;font-size:16px;width:100%;">Se connecter</button>
			</div>
		</form>
		<div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;">
			<p style="margin:0;color:#6b7280;">Pas encore de compte ?</p>
			<a href="<?php echo esc_url(msp_get_page_url_by_slug('register')); ?>" style="color:#2563eb;text-decoration:none;font-weight:600;">Créer un compte</a>
		</div>
	</div>
	<script>
	// AJAX progressive enhancement (fallback غیر-AJAX فعال است)
	document.getElementById('mspLoginForm').addEventListener('submit', function(e) {
		<?php /* اگر AJAX کار نکرد، فرم به صورت معمول POST می‌شود و هندلر init لاگین می‌کند */ ?>
		e.preventDefault();
		const form = this;
		const fd = new FormData(form);
		fd.append('action', 'msp_login_user');
		fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd, credentials:'same-origin' })
		.then(r => r.json()).then(d => {
			if (d.success) { window.location.href = d.data.redirect_url; }
			else { window.location.href = '<?php echo get_permalink(); ?>?error=' + encodeURIComponent(d.data); }
		}).catch(() => { form.submit(); });
	});
	</script>
	<?php return ob_get_clean();
});
add_action('wp_ajax_msp_login_user', 'msp_login_user_handler');
add_action('wp_ajax_nopriv_msp_login_user', 'msp_login_user_handler');
function msp_login_user_handler() {
	if (!wp_verify_nonce($_POST['msp_login_nonce'] ?? '', 'msp_login_nonce')) wp_send_json_error('Erreur de sécurité');
	$username = sanitize_text_field($_POST['msp_username'] ?? ''); $password = $_POST['msp_password'] ?? '';
	$recaptcha = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
	$redirect_url = isset($_POST['redirect']) ? esc_url_raw($_POST['redirect']) : home_url('/dashboard/');
	if (!$username || !$password) wp_send_json_error('Tous les champs sont requis');
	if (!msp_verify_recaptcha($recaptcha)) wp_send_json_error('reCAPTCHA invalide');

	$user = wp_signon(['user_login'=>$username,'user_password'=>$password,'remember'=>true], is_ssl());
	if (is_wp_error($user)) wp_send_json_error('Identifiants incorrects');

	if (get_option('msp_email_verify_required','1')==='1' && get_user_meta($user->ID,'msp_email_verified',true)!=='1') {
		wp_logout();
		wp_send_json_error('Veuillez vérifier votre email avant de vous connecter.');
	}
	// تضمین ست شدن کوکی روی برخی میزبان‌ها/پراکسی‌ها
	wp_set_auth_cookie($user->ID, true, is_ssl());
	wp_set_current_user($user->ID);
	do_action('wp_login', $user->user_login, $user);

	wp_send_json_success([ 'redirect_url' => $redirect_url, 'message' => 'Connexion réussie' ]);
}

/* Registration (AJAX) with HTML email */
add_shortcode('msp_register_form', function () {
	if (is_user_logged_in()) { wp_redirect(home_url('/dashboard/')); exit; }
	$site_key = esc_attr(get_option('msp_recaptcha_site',''));
	ob_start(); ?>
	<div style="max-width:420px;margin:40px auto;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.1);padding:24px;">
		<h2 style="margin:0 0 16px;text-align:center;color:#111827">Créer un compte</h2>
		<form id="mspRegisterForm" method="post" style="display:grid;gap:12px;">
			<?php wp_nonce_field('msp_register_nonce','msp_register_nonce'); ?>
			<label style="font-weight:600;color:#374151">Nom d'utilisateur *</label>
			<input type="text" name="username" required style="padding:10px;border:1px solid #d1d5db;border-radius:8px">
			<label style="font-weight:600;color:#374151">Email *</label>
			<input type="email" name="email" required style="padding:10px;border:1px solid #d1d5db;border-radius:8px">
			<label style="font-weight:600;color:#374151">Mot de passe *</label>
			<input type="password" name="password" required style="padding:10px;border:1px solid #d1d5db;border-radius:8px">
			<?php if ($site_key): ?>
			<div class="g-recaptcha" data-sitekey="<?php echo $site_key; ?>"></div>
			<script src="https://www.google.com/recaptcha/api.js" async defer></script>
			<?php endif; ?>
			<button type="submit" style="margin-top:6px;background:#2563eb;color:#fff;border:none;border-radius:8px;padding:12px;font-weight:700;cursor:pointer">Créer le compte</button>
			<div id="mspRegMsg" style="display:none;margin-top:8px;color:#b91c1c;font-weight:600"></div>
		</form>
		<p style="margin-top:12px;text-align:center;color:#6b7280">Déjà inscrit ? <a href="<?php echo esc_url(msp_get_page_url_by_slug('login')); ?>">Connexion</a></p>
	</div>
	<script>
	document.getElementById('mspRegisterForm').addEventListener('submit', function(e){
		e.preventDefault();
		const fd = new FormData(this);
		fd.append('action','msp_register_user');
		<?php if ($site_key): ?>fd.append('g-recaptcha-response', grecaptcha.getResponse());<?php endif; ?>
		fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd,credentials:'same-origin'})
		.then(r=>r.json()).then(j=>{
			if(j && j.success){ window.location.href = j.data.redirect; }
			else{
				const m=document.getElementById('mspRegMsg');
				m.textContent = (j && j.data) ? j.data : 'Erreur';
				m.style.display='block';
			}
		}).catch(()=>{ const m=document.getElementById('mspRegMsg'); m.textContent='Erreur réseau'; m.style.display='block'; });
	});
	</script>
	<?php return ob_get_clean();
});
add_action('wp_ajax_nopriv_msp_register_user','msp_register_user_handler');
function msp_register_user_handler(){
	if (!isset($_POST['msp_register_nonce']) || !wp_verify_nonce($_POST['msp_register_nonce'],'msp_register_nonce')) {
		wp_send_json_error('Erreur de sécurité',400);
	}
	$recaptcha = sanitize_text_field($_POST['g-recaptcha-response'] ?? '');
	if (!msp_verify_recaptcha($recaptcha)) wp_send_json_error('reCAPTCHA invalide',400);

	$username = sanitize_user($_POST['username'] ?? '');
	$email    = sanitize_email($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';
	if (!$username || !$email || !$password){ wp_send_json_error('Champs requis manquants',400); }
	if (username_exists($username)){ wp_send_json_error('Nom d’utilisateur déjà pris',400); }
	if (email_exists($email)){ wp_send_json_error('Email déjà utilisé',400); }

	$user_id = wp_create_user($username, $password, $email);
	if (is_wp_error($user_id)){ wp_send_json_error('Échec de création de compte',500); }

	if (get_option('msp_email_verify_required','1')==='1') {
		$token = wp_generate_password(32, false, false);
		update_user_meta($user_id,'msp_email_verified','0');
		update_user_meta($user_id,'msp_verif_token',$token);
		$link = add_query_arg(['msp_verify'=>'1','user'=>$user_id,'token'=>$token], home_url('/'));
		add_filter('wp_mail_content_type', function(){ return 'text/html; charset=UTF-8'; });
		wp_mail($email, 'Vérifiez votre email', 'Cliquez pour vérifier votre compte: <a href="'.esc_url($link).'">'.esc_html($link).'</a>');
		remove_filter('wp_mail_content_type', '__return_false');
		wp_send_json_success(['redirect'=> msp_get_page_url_by_slug('login').'?checkemail=1' ]);
	}

	$creds = ['user_login'=>$username,'user_password'=>$password,'remember'=>true];
	$user = wp_signon($creds, is_ssl());
	if (is_wp_error($user)) {
		wp_send_json_success(['redirect'=> msp_get_page_url_by_slug('login') ]);
	} else {
		wp_send_json_success(['redirect'=> msp_get_page_url_by_slug('dashboard') ]);
	}
}
add_filter('register_url', function($url){
	$page = get_page_by_path('register');
	return $page ? get_permalink($page->ID) : $url;
});

/* CPTs */
add_action('init', function() {
	register_post_type('ecard_digital_card', [
		'labels' => [
			'name' => 'Cartes Digitales',
			'singular_name' => 'Carte Digitale',
			'add_new' => 'Ajouter une carte',
			'add_new_item' => 'Ajouter une nouvelle carte',
			'edit_item' => 'Modifier la carte',
			'new_item' => 'Nouvelle carte',
			'view_item' => 'Voir la carte',
			'search_items' => 'Rechercher des cartes',
			'not_found' => 'Aucune carte trouvée',
			'not_found_in_trash' => 'Aucune carte trouvée dans la corbeille'
		],
		'public' => true,
		'has_archive' => true,
		'supports' => ['title', 'editor', 'thumbnail'],
		'menu_icon' => 'dashicons-id-alt',
		'rewrite' => ['slug' => 'digital-card']
	]);
	register_post_type('msp_ar_experience', [
		'labels' => [
			'name' => 'Expériences AR',
			'singular_name' => 'Expérience AR',
			'add_new' => 'Ajouter une expérience',
			'add_new_item' => 'Ajouter une nouvelle expérience',
			'edit_item' => 'Modifier l\'expérience',
			'new_item' => 'Nouvelle expérience',
			'view_item' => 'Voir l\'expérience',
			'search_items' => 'Rechercher des expériences',
			'not_found' => 'Aucune expérience trouvée',
			'not_found_in_trash' => 'Aucune expérience trouvée dans la corbeille'
		],
		'public' => true,
		'has_archive' => true,
		'supports' => ['title', 'editor', 'thumbnail'],
		'menu_icon' => 'dashicons-video-alt3',
		'rewrite' => ['slug' => 'ar-experience']
	]);
});
/* Digital Card customizer container */
add_shortcode('digital_card_iframe', function() {
	$iframe_url = msp_assets_url('dc-customizer.html');
	ob_start(); ?>
	<div style="position:relative">
		<iframe id="mspDcIframe" src="<?php echo esc_url($iframe_url); ?>" style="width:100%;height:680px;border:none;border-radius:8px;"></iframe>
		<div style="display:flex;gap:10px;margin-top:10px">
			<button id="mspSaveDcDesign" class="button" style="background:#111827;color:#fff;border:none;border-radius:8px;padding:10px 12px;cursor:pointer;">Sauvegarder le design</button>
			<span id="mspSaveDcMsg" style="display:none;color:#16a34a;font-weight:600">Sauvegardé.</span>
		</div>
	</div>
	<script>
	document.getElementById('mspSaveDcDesign').addEventListener('click', function(){
		let design=null;
		try{ design = localStorage.getItem('dc_design'); }catch(e){}
		if(!design){ alert("Aucun design trouvé."); return; }
		const fd=new FormData();
		fd.append('action','msp_save_user_design');
		fd.append('type','card');
		fd.append('payload', design);
		fd.append('security','<?php echo wp_create_nonce('msp_save_design'); ?>');
		fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd,credentials:'same-origin'})
		.then(r=>r.json()).then(j=>{ if(j.success){ const m=document.getElementById('mspSaveDcMsg'); m.style.display='inline'; setTimeout(()=>m.style.display='none',2000);} else { alert('Erreur de sauvegarde'); }})
		.catch(()=>alert('Erreur réseau'));
	});
	</script>
	<?php return ob_get_clean();
});

/* Save user designs */
add_action('wp_ajax_msp_save_user_design', function(){
	if(!is_user_logged_in()) wp_send_json_error(['msg'=>'noauth'],403);
	if(!wp_verify_nonce($_POST['security'] ?? '', 'msp_save_design')) wp_send_json_error(['msg'=>'badnonce'],400);
	$type = sanitize_text_field($_POST['type'] ?? '');
	$payload = wp_unslash($_POST['payload'] ?? '');
	$user_id = get_current_user_id();
	if(!$type || !$payload) wp_send_json_error(['msg'=>'invalid'],400);
	if($type==='card'){
		$list = get_user_meta($user_id, 'msp_saved_cards', true);
		$list = is_array($list)?$list:[];
		$list[] = ['ts'=>current_time('mysql'), 'design'=>$payload];
		if(count($list)>20) $list = array_slice($list, -20);
		update_user_meta($user_id, 'msp_saved_cards', $list);
	}else if($type==='stamp'){
		$list = get_user_meta($user_id, 'msp_saved_stamps', true);
		$list = is_array($list)?$list:[];
		$list[] = ['ts'=>current_time('mysql'), 'customization'=>$payload];
		if(count($list)>20) $list = array_slice($list, -20);
		update_user_meta($user_id, 'msp_saved_stamps', $list);
	}else{
		wp_send_json_error(['msg'=>'type'],400);
	}
	wp_send_json_success(['ok'=>true]);
});

/* Digital Card form (step 2) */
add_shortcode('dc_user_form', function() {
	msp_redirect_to_login();
	$from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : '';
	ob_start(); ?>
	<div style="max-width:980px;margin:32px auto;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.1);padding:24px;position:relative;">
		<h2 style="margin:0 0 20px;color:#1f2937;text-align:center;">Créer votre carte digitale</h2>
		<div style="display:grid;grid-template-columns:1fr 360px;gap:16px">
			<form id="dcUserForm" method="post" enctype="multipart/form-data" style="display:grid;gap:16px;">
				<?php wp_nonce_field('dc_card_nonce', 'dc_card_nonce'); ?>
				<input type="hidden" name="dc_design_json" id="dc_design_json" value="">
				<input type="hidden" name="no_redirect" value="1">
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Nom complet *</label><input type="text" name="dc_name" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Fonction *</label><input type="text" name="dc_title" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
				</div>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Téléphone *</label><input type="tel" name="dc_phone" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Email *</label><input type="email" name="dc_email" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
				</div>
				<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Adresse</label><textarea name="dc_address" rows="3" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;resize:vertical;"></textarea></div>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Site web</label><input type="url" name="dc_website" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">LinkedIn</label><input type="url" name="dc_linkedin" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
				</div>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Logo de l'entreprise</label><input type="file" name="dc_company_logo" accept="image/*" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
					<div><label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Photo personnelle</label><input type="file" name="dc_personal_photo" accept="image/*" style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;"></div>
				</div>

				<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
					<button type="submit" class="button button-primary" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:12px 16px;">Enregistrer</button>
					<a id="btnViewCard" href="#" target="_blank" style="display:none;background:#10b981;color:#fff;border:none;border-radius:8px;padding:12px 16px;text-decoration:none;">Afficher la carte</a>
					<?php if ($from==='ar'): ?>
					<a id="btnBackAR" href="#" style="display:none;background:#111827;color:#fff;border:none;border-radius:8px;padding:12px 16px;text-decoration:none;">Retour à l'AR</a>
					<?php endif; ?>
				</div>
				<div id="dcSavedLink" style="display:none;margin-top:8px;color:#374151;font-weight:600"></div>
			</form>

			<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
				<div style="font-weight:700;margin-bottom:6px">Aperçu</div>
				<div id="dcPreview" style="width:100%;max-width:360px;aspect-ratio:1.586;border:1px dashed #cbd5e1;border-radius:12px;background:#fff;overflow:hidden;position:relative">
					<div id="dcPreInner" style="position:absolute;inset:0;padding:12px"></div>
				</div>
			</div>
		</div>
	</div>
	<script>
	(function(){
		const form=document.getElementById('dcUserForm');
		const prev=document.getElementById('dcPreInner');
		function set(k, def){ const el=form.querySelector(`[name="${k}"]`); return (el && el.value.trim()) || def; }
		function draw(){
			prev.innerHTML='';
			const nm=set('dc_name','Nom Prénom');
			const tl=set('dc_title','Titre');
			const em=set('dc_email','');
			const ph=set('dc_phone','');
			const ww=set('dc_website','');
			const ad=set('dc_address','');
			const top=document.createElement('div'); top.style.font='800 18px system-ui'; top.textContent=nm; prev.appendChild(top);
			const meta=document.createElement('div'); meta.style.opacity='.8'; meta.style.marginBottom='8px'; meta.textContent=tl; prev.appendChild(meta);
			[['✉️',em],['📞',ph],['🌐',ww],['📍',ad]].forEach(([i,t])=>{ if(!t) return; const r=document.createElement('div'); r.textContent=i+' '+t; r.style.margin='2px 0'; prev.appendChild(r); });
		}
		form.addEventListener('input', draw); draw();
		try{ const saved = localStorage.getItem('dc_design'); if (saved) document.getElementById('dc_design_json').value = saved; }catch(e){}
		form.addEventListener('submit', function(e){
			e.preventDefault();
			const fd = new FormData(form);
			fd.append('action','dc_create_card');
			fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method:'POST', body:fd, credentials:'same-origin' })
			.then(r=>r.json()).then(d=>{
				if (!d.success) { alert('Erreur: '+d.data); return; }
				const view = d.data.view_url; const ar = d.data.ar_url;
				const link = document.getElementById('dcSavedLink');
				const btnV = document.getElementById('btnViewCard');
				<?php if ($from==='ar'): ?>const btnB = document.getElementById('btnBackAR');<?php endif; ?>
				link.textContent = 'Lien: ' + view; link.style.display='block';
				btnV.href = view; btnV.style.display='inline-block';
				<?php if ($from==='ar'): ?>btnB.href = ar; btnB.style.display='inline-block';<?php endif; ?>
			}).catch(()=>alert('Erreur réseau'));
		});
	})();
	</script>
	<?php return ob_get_clean();
});
add_action('wp_ajax_dc_create_card', 'dc_create_card_handler');
function dc_create_card_handler() {
	if (!wp_verify_nonce($_POST['dc_card_nonce'] ?? '', 'dc_card_nonce')) wp_send_json_error('Erreur de sécurité');
	if (!msp_is_user_logged_in()) wp_send_json_error('Utilisateur non connecté');
	$user_id = get_current_user_id();
	$name = sanitize_text_field($_POST['dc_name'] ?? ''); $title = sanitize_text_field($_POST['dc_title'] ?? '');
	$phone = sanitize_text_field($_POST['dc_phone'] ?? ''); $email = sanitize_email($_POST['dc_email'] ?? '');
	$address = sanitize_textarea_field($_POST['dc_address'] ?? ''); $website = esc_url_raw($_POST['dc_website'] ?? '');
	$linkedin = esc_url_raw($_POST['dc_linkedin'] ?? ''); $design = wp_unslash($_POST['dc_design_json'] ?? '');
	if (!$name || !$title || !$phone || !$email) wp_send_json_error('Veuillez remplir tous les champs obligatoires');
	$company_logo = ''; $personal_photo = '';
	if (!empty($_FILES['dc_company_logo']['name'])) { $u = wp_handle_upload($_FILES['dc_company_logo'], ['test_form' => false]); if (!isset($u['error'])) $company_logo = $u['url']; }
	if (!empty($_FILES['dc_personal_photo']['name'])) { $u = wp_handle_upload($_FILES['dc_personal_photo'], ['test_form' => false]); if (!isset($u['error'])) $personal_photo = $u['url']; }
	$post_id = wp_insert_post([
		'post_title'  => $name . ' - ' . $title,
		'post_content'=> '',
		'post_status' => 'publish',
		'post_type'   => 'ecard_digital_card',
		'post_author' => $user_id
	]);
	if (is_wp_error($post_id)) wp_send_json_error('Erreur lors de la création de la carte');
	update_post_meta($post_id, 'dc_name', $name);
	update_post_meta($post_id, 'dc_title', $title);
	update_post_meta($post_id, 'dc_phone', $phone);
	update_post_meta($post_id, 'dc_email', $email);
	update_post_meta($post_id, 'dc_address', $address);
	update_post_meta($post_id, 'dc_website', $website);
	update_post_meta($post_id, 'dc_linkedin', $linkedin);
	update_post_meta($post_id, 'dc_company_logo', $company_logo);
	update_post_meta($post_id, 'dc_personal_photo', $personal_photo);
	update_post_meta($post_id, 'dc_user_id', $user_id);
	update_post_meta($post_id, 'dc_created_at', current_time('mysql'));
	if (!empty($design)) update_post_meta($post_id, 'dc_design_json', $design);
	msp_track_card_action($post_id, $user_id, 'card_created');
	msp_chat_send_invoice_message($user_id, $post_id, [ 'name'=>$name, 'email'=>$email, 'phone'=>$phone ]);
	$view_url = msp_get_page_url_by_slug('card-view') . '?card_id=' . $post_id;
	$ar_url = msp_get_page_url_by_slug('ar-form') . '?card_id=' . $post_id;
	if (!empty($_POST['no_redirect'])) {
		wp_send_json_success(['view_url'=>$view_url,'ar_url'=>$ar_url]);
	} else {
		wp_send_json_success(['redirect_url'=>$ar_url, 'message'=>'Carte créée avec succès']);
	}
}

/* Dashboard */
add_shortcode('msp_user_dashboard', function() {
	msp_redirect_to_login();
	$user_id = get_current_user_id();
	$user = wp_get_current_user();

	$detail_card = isset($_GET['ar_card']) ? intval($_GET['ar_card']) : 0;
	if ($detail_card) {
		if (intval(get_post_field('post_author', $detail_card)) !== $user_id) return '<div style="max-width:900px;margin:24px auto;background:#fff;border-radius:12px;padding:16px;">Accès refusé.</div>';
		$q = new WP_Query([
			'post_type' => 'msp_ar_experience',
			'author' => $user_id,
			'posts_per_page' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => [['key'=>'ar_card_id','value'=>$detail_card,'compare'=>'=']]
		]);
		$targets = [];
		if ($q->have_posts()) {
			$q->the_post();
			$raw = get_post_meta(get_the_ID(), 'ar_targets', true);
			$targets = json_decode($raw, true);
			wp_reset_postdata();
		}
		ob_start(); ?>
		<div style="max-width:1000px;margin:24px auto;background:#fff;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,.08);padding:16px;">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
				<h2 style="margin:0;">Détails AR — Carte #<?php echo $detail_card; ?></h2>
				<div style="display:flex;gap:8px;">
					<a class="button" href="<?php echo esc_url(msp_get_page_url_by_slug('ar-form').'?card_id='.$detail_card); ?>" style="background:#111827;color:#fff;border:none;border-radius:8px;padding:8px 12px;text-decoration:none;">Modifier</a>
					<a class="button" href="<?php echo esc_url(msp_get_page_url_by_slug('dashboard')); ?>" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;text-decoration:none;">Retour</a>
				</div>
			</div>
			<table style="width:100%;border-collapse:collapse;">
				<thead><tr style="background:#f8fafc">
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Cible</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Image</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Vidéo FR</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Vidéo EN</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Vidéo DE</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Vidéo ES</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Modèle 3D</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Musique</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Lien Business</th>
					<th style="text-align:left;border:1px solid #e5e7eb;padding:8px;">Formulaire Google</th>
				</tr></thead>
				<tbody>
				<?php for($i=1;$i<=10;$i++):
					$it = isset($targets[$i]) ? $targets[$i] : null;
					$img = $it['image'] ?? '';
					$vfr = $it['video_fr'] ?? ($it['video_fr_url'] ?? '');
					$ven = $it['video_en'] ?? ($it['video_en_url'] ?? '');
					$vde = $it['video_de'] ?? ($it['video_de_url'] ?? '');
					$ves = $it['video_es'] ?? ($it['video_es_url'] ?? '');
					$glb = $it['model'] ?? ($it['model_glb_url'] ?? '');
					$aud = $it['audio'] ?? ($it['music_url'] ?? '');
					$biz = $it['business_card_url'] ?? '';
					$gfs = $it['google_survey_url'] ?? '';
				?>
				<tr>
					<td style="border:1px solid #e5e7eb;padding:8px;">Cible <?php echo $i; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $img?'<img src="'.esc_url($img).'" style="width:80px;height:80px;object-fit:cover;border-radius:6px;">':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $vfr?'<a href="'.esc_url($vfr).'" target="_blank">Présent</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $ven?'<a href="'.esc_url($ven).'" target="_blank">Présent</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $vde?'<a href="'.esc_url($vde).'" target="_blank">Présent</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $ves?'<a href="'.esc_url($ves).'" target="_blank">Présent</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $glb?'<a href="'.esc_url($glb).'" target="_blank">Présent</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $aud?'<a href="'.esc_url($aud).'" target="_blank">Présent</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $biz?'<a href="'.esc_url($biz).'" target="_blank">Ouvrir</a>':'—'; ?></td>
					<td style="border:1px solid #e5e7eb;padding:8px;"><?php echo $gfs?'<a href="'.esc_url($gfs).'" target="_blank">Ouvrir</a>':'—'; ?></td>
				</tr>
				<?php endfor; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	$cards = get_posts([
		'post_type' => 'ecard_digital_card',
		'author' => $user_id,
		'posts_per_page' => -1,
		'post_status' => 'publish'
	]);
	$cards_saved = get_user_meta($user_id, 'msp_saved_cards', true); if(!is_array($cards_saved)) $cards_saved=[];
	$stamps_saved = get_user_meta($user_id, 'msp_saved_stamps', true); if(!is_array($stamps_saved)) $stamps_saved=[];

	ob_start(); ?>
	<div class="msp-dashboard" style="max-width:1200px;margin:0 auto;padding:20px;">
		<div style="background:#fff;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,.1);padding:24px;margin-bottom:24px;">
			<h1 style="margin:0 0 8px;color:#1f2937;font-size:28px;">Tableau de bord</h1>
			<p style="margin:0;color:#6b7280;">Bienvenue, <?php echo esc_html($user->display_name); ?></p>
		</div>

		<div style="background:#fff;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,.1);overflow:hidden;">
			<div style="display:flex;border-bottom:1px solid #e5e7eb;">
				<button class="tab-btn active" data-tab="cards" style="flex:1;padding:16px;background:none;border:none;cursor:pointer;font-weight:600;color:#374151;">Mes Cartes</button>
				<button class="tab-btn" data-tab="designs" style="flex:1;padding:16px;background:none;border:none;cursor:pointer;font-weight:600;color:#6b7280;">Mes designs sauvegardés</button>
			</div>

			<div id="cards-tab" class="tab-content" style="padding:24px;">
				<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
					<h2 style="margin:0;color:#1f2937;">Mes Cartes Digitales</h2>
					<a href="<?php echo esc_url(msp_get_page_url_by_slug('digital-card')); ?>" style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">+ Nouvelle carte</a>
				</div>
				<?php if (empty($cards)): ?>
					<div style="text-align:center;padding:40px;color:#6b7280;">Aucune carte pour le moment.</div>
				<?php else: ?>
					<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
						<?php foreach ($cards as $card): ?>
						<div style="border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
							<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
								<div>
									<h3 style="margin:0 0 4px;color:#1f2937;font-size:18px;"><?php echo esc_html(get_post_meta($card->ID, 'dc_name', true)); ?></h3>
									<p style="margin:0;color:#6b7280;font-size:14px;"><?php echo esc_html(get_post_meta($card->ID, 'dc_title', true)); ?></p>
								</div>
								<div style="display:flex;gap:8px;">
									<a href="<?php echo esc_url(msp_get_page_url_by_slug('card-view').'?card_id='.$card->ID); ?>" class="button" style="background:#3b82f6;color:#fff;border:none;padding:8px;border-radius:4px;text-decoration:none;" target="_blank">Voir</a>
									<a href="<?php echo esc_url(msp_get_page_url_by_slug('dashboard').'?ar_card='.$card->ID); ?>" class="button" style="background:#10b981;color:#fff;border:none;padding:8px;border-radius:4px;text-decoration:none;">AR</a>
									<a href="<?php echo esc_url(msp_get_page_url_by_slug('card-preview').'?card_id='.$card->ID); ?>" class="button" style="background:#111827;color:#fff;border:none;padding:8px;border-radius:4px;text-decoration:none;">Aperçu/Thème</a>
								</div>
							</div>
							<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;font-size:14px;">
								<div><span style="color:#6b7280;">Téléphone:</span><br><span><?php echo esc_html(get_post_meta($card->ID, 'dc_phone', true)); ?></span></div>
								<div><span style="color:#6b7280;">Email:</span><br><span><?php echo esc_html(get_post_meta($card->ID, 'dc_email', true)); ?></span></div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div id="designs-tab" class="tab-content" style="padding:24px;display:none;">
				<h2 style="margin:0 0 16px;color:#1f2937;">Mes designs sauvegardés</h2>
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
					<div>
						<h3 style="margin:0 0 8px;">Cartes</h3>
						<?php if(empty($cards_saved)): ?>
							<div style="color:#6b7280;">Aucun design de carte sauvegardé.</div>
						<?php else: ?>
							<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
								<?php foreach(array_reverse($cards_saved) as $idx=>$it): ?>
									<div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;">
										<div style="font-size:12px;color:#6b7280;margin-bottom:6px;"><?php echo esc_html($it['ts']); ?></div>
										<div class="msp-card-preview" data-design='<?php echo esc_attr($it['design']); ?>' style="width:100%;aspect-ratio:1.586;border:1px dashed #e5e7eb;border-radius:8px;position:relative;background:#fafafa;overflow:hidden;"></div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<div>
						<h3 style="margin:0 0 8px;">Tampons</h3>
						<?php if(empty($stamps_saved)): ?>
							<div style="color:#6b7280;">Aucune personnalisation de tampon sauvegardée.</div>
						<?php else: ?>
							<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
								<?php foreach(array_reverse($stamps_saved) as $it):
									$obj = json_decode($it['customization'], true);
									$img = is_array($obj) && isset($obj['selections']['preview_data_url']) ? $obj['selections']['preview_data_url'] : '';
								?>
									<div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;">
										<div style="font-size:12px;color:#6b7280;margin-bottom:6px;"><?php echo esc_html($it['ts']); ?></div>
										<?php if ($img): ?>
											<img src="<?php echo esc_url($img); ?>" alt="" style="width:100%;height:auto;border-radius:8px;border:1px solid #e5e7eb">
										<?php else: ?>
											<pre style="white-space:pre-wrap;font-size:12px;max-height:160px;overflow:auto;background:#f8fafc;padding:8px;border-radius:6px;"><?php echo esc_html($it['customization']); ?></pre>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</div>
	</div>
	<script>
	document.querySelectorAll('.tab-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			const tab = this.dataset.tab;
			document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.style.color = '#6b7280'; });
			this.classList.add('active'); this.style.color = '#374151';
			document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
			document.getElementById(tab + '-tab').style.display = 'block';
		});
	});
	(function(){
		const nodes = document.querySelectorAll('.msp-card-preview');
		nodes.forEach(node=>{
			let design=null;
			try{ design = JSON.parse(node.getAttribute('data-design')); }catch(e){}
			if(!design){ node.style.background='#fff'; return; }
			const inner=document.createElement('div'); inner.style.position='absolute'; inner.style.inset='0'; inner.style.background='#fff'; node.appendChild(inner);
			if (design.logo){
				const lg=document.createElement('div');
				lg.style.position='absolute'; lg.style.left='10px'; lg.style.top='10px'; lg.style.width='56px'; lg.style.height='56px';
				lg.style.backgroundImage=`url(${design.logo})`; lg.style.backgroundSize='contain'; lg.style.backgroundRepeat='no-repeat'; lg.style.backgroundPosition='center';
				inner.appendChild(lg);
			}
			if (design.textLines && Array.isArray(design.textLines)){
				design.textLines.slice(0,4).forEach((t,i)=>{
					const el=document.createElement('div');
					el.textContent=t||''; el.style.position='absolute'; el.style.left='12px'; el.style.top=(80+i*20)+'px';
					el.style.font='bold 12px system-ui'; inner.appendChild(el);
				});
			}
		});
	})();
	</script>
	<?php return ob_get_clean();
});

/* Stamp customizer container */
add_shortcode('stamp_customizer_iframe', function() {
	$iframe_url = msp_assets_url('customizer.html');
	$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

	ob_start(); ?>
	<div style="max-width:1200px;margin:0 auto;padding:20px;">

		<?php if ($product_id && function_exists('wc_get_product')):
			$product = wc_get_product($product_id);
			if ($product):
				$image = get_the_post_thumbnail_url($product_id, 'large');
				$desc  = wpautop(wp_kses_post($product->get_description()));
				$title = esc_html($product->get_title());
				$price_html = $product->get_price_html();
				$price_base = (float) $product->get_price();

				$pt = rawurlencode($title);
				$pd = rawurlencode(wp_strip_all_tags($product->get_short_description() ?: $product->get_description()));
				$pi = rawurlencode($image ?: '');
				$bp = $price_base;
				$qp = (float) get_post_meta($product_id, '_msp_price_qr', true);
				$lp = (float) get_post_meta($product_id, '_msp_price_logo', true);
				$lnp= (float) get_post_meta($product_id, '_msp_price_text_line', true);
				$fp = (float) get_post_meta($product_id, '_msp_price_frame', true);
				$cp = (float) get_post_meta($product_id, '_msp_price_curve_text', true);
				$iframe_qs = "?pt={$pt}&pd={$pd}&pi={$pi}&bp={$bp}&qp={$qp}&lp={$lp}&lnp={$lnp}&fp={$fp}&cp={$cp}";
				$iframe_url = $iframe_url . $iframe_qs;
				?>
				<div style="display:flex;gap:16px;align-items:center;margin-bottom:16px">
					<?php if ($image): ?>
						<img src="<?php echo esc_url($image); ?>" alt="<?php echo $title; ?>" style="width:140px;height:140px;object-fit:cover;border-radius:8px;border:1px solid #eee" />
					<?php endif; ?>
					<div>
						<h2 style="margin:0 0 8px 0"><?php echo $title; ?></h2>
						<?php if ($price_html): ?><div style="font-weight:bold;color:#0a7"><?php echo $price_html; ?></div><?php endif; ?>
						<div style="color:#444;max-width:700px"><?php echo $desc; ?></div>
					</div>
				</div>
		<?php endif; endif; ?>

		<iframe id="mspStampIframe" src="<?php echo esc_url($iframe_url); ?>" style="width:100%;height:800px;border:none;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,.06);"></iframe>

		<?php
		$price_base = (!empty($product) && is_a($product,'WC_Product')) ? (float) $product->get_price() : 0;
		$ink_map   = msp_parse_kv_lines_to_map(json_decode(get_option('msp_stamp_price_ink_map','[]'), true));
		$shape_map = msp_parse_kv_lines_to_map(json_decode(get_option('msp_stamp_price_shape_map','[]'), true));
		$size_map  = msp_parse_kv_lines_to_map(json_decode(get_option('msp_stamp_price_size_map','[]'), true));
		?>
		<div id="msp-invoice" data-product-id="<?php echo intval($product_id); ?>"
			data-product-base="<?php echo esc_attr($price_base); ?>"
			data-ink-map="<?php echo esc_attr(wp_json_encode($ink_map)); ?>"
			data-shape-map="<?php echo esc_attr(wp_json_encode($shape_map)); ?>"
			data-size-map="<?php echo esc_attr(wp_json_encode($size_map)); ?>"
			style="margin-top:16px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;box-shadow:0 8px 24px rgba(0,0,0,.05)">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;flex-wrap:wrap">
				<h3 style="margin:0">Devis en direct</h3>
				<div style="display:flex;gap:8px;flex-wrap:wrap">
					<button id="mspSaveStampBtn" style="background:#111827;color:#fff;border:none;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:600">Save</button>
					<a id="mspPayBtn" href="#" style="display:none;background:#10b981;color:#fff;border:none;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:600;text-decoration:none">Payer</a>
					<button id="mspGoArBtn" style="background:#2563eb;color:#fff;border:none;border-radius:10px;padding:10px 12px;cursor:pointer;font-weight:600">AR</button>
				</div>
			</div>
			<table style="width:100%;border-collapse:collapse">
				<thead><tr><th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Élément</th><th style="text-align:right;padding:8px;border-bottom:1px solid #eee">Prix</th></tr></thead>
				<tbody id="msp-invoice-items"></tbody>
				<tfoot><tr><td style="padding:8px;border-top:1px solid #eee;text-align:right;font-weight:700">Total</td><td id="msp-invoice-total" style="padding:8px;border-top:1px solid #eee;text-align:right;font-weight:700">0</td></tr></tfoot>
			</table>
			<p style="margin-top:8px;color:#6b7280;font-size:12px">Le total inclut le prix du produit + options sélectionnées (encre، forme، taille).</p>
		</div>
	</div>
	<script>
	(function(){
		const invoice = document.getElementById('msp-invoice');
		const itemsBody = document.getElementById('msp-invoice-items');
		const totalEl = document.getElementById('msp-invoice-total');
		const maps = {
			ink:  JSON.parse(invoice.dataset.inkMap || '{}'),
			shape:JSON.parse(invoice.dataset.shapeMap || '{}'),
			size: JSON.parse(invoice.dataset.sizeMap || '{}'),
		};
		const price = { base: parseFloat(invoice.dataset.productBase || '0') || 0 };
		let currentSelections = { ink_color:null, shape:null, size:null, preview_data_url:'', title:'Mon tampon' };

		function money(v){ try { return (new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR'})).format(v); } catch(e){ return Number(v).toFixed(2); } }
		function p(map, key){ if(!key) return 0; key=String(key).toLowerCase(); return parseFloat(map[key] || 0) || 0; }
		function rebuildInvoice(){
			let rows=[], total=0;
			if (price.base>0){ rows.push(['Produit', price.base]); total+=price.base; }
			if (currentSelections.ink_color){ const v=p(maps.ink,currentSelections.ink_color); if (v>0){ rows.push(['Encre: '+currentSelections.ink_color, v]); total+=v; } }
			if (currentSelections.shape){ const v=p(maps.shape,currentSelections.shape); if (v>0){ rows.push(['Forme: '+currentSelections.shape, v]); total+=v; } }
			if (currentSelections.size){ const v=p(maps.size,currentSelections.size); if (v>0){ rows.push(['Taille: '+currentSelections.size, v]); total+=v; } }
			itemsBody.innerHTML = rows.map(r=>`<tr><td style="padding:8px;border-bottom:1px solid #f1f5f9">${r[0]}</td><td style="padding:8px;border-bottom:1px solid #f1f5f9;text-align:right">${money(r[1])}</td></tr>`).join('');
			totalEl.textContent = money(total);
		}
		window.addEventListener('message', function(ev){
			const msg = ev.data || {};
			if (msg.type==='stamp_customization_update' && msg.customization){
				const c=msg.customization;
				currentSelections.ink_color = c.inkColor || null;
				currentSelections.shape = c.shape || null;
				currentSelections.size = c.size || null;
				currentSelections.title = typeof c.title==='string' && c.title.trim()? c.title.trim() : currentSelections.title;
				rebuildInvoice();
			}
			if (msg.type==='msp_preview_updated' && msg.data && msg.data.preview_data_url){
				currentSelections.preview_data_url = msg.data.preview_data_url;
			}
		});
		document.getElementById('mspSaveStampBtn').addEventListener('click', function(){
			const payload = {
				title: currentSelections.title,
				selections: currentSelections,
				product_id: parseInt(invoice.dataset.productId || '0', 10),
				total_eur: document.getElementById('msp-invoice-total')?.textContent || ''
			};
			const fd = new FormData();
			fd.append('action','msp_save_user_design');
			fd.append('type','stamp');
			fd.append('payload', JSON.stringify(payload));
			fd.append('security','<?php echo wp_create_nonce('msp_save_design'); ?>');
			fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd})
			.then(r=>r.json()).then(j=>{
				if(j && j.success){
					alert('Design sauvegardé. Vous pouvez payer ou continuer vers AR.');
					document.getElementById('mspPayBtn').href = '<?php echo esc_url(home_url('/panier')); ?>';
					document.getElementById('mspPayBtn').style.display = 'inline-block';
				} else { alert((j&&j.data)||'Échec de sauvegarde'); }
			}).catch(()=>alert('Erreur réseau'));
		});
		document.getElementById('mspGoArBtn').addEventListener('click', function(){
			const pr = encodeURIComponent(currentSelections.preview_data_url || '');
			window.location.href = '<?php echo esc_url(msp_get_page_url_by_slug('ar-form')); ?>?stamp_preview='+pr;
		});
		rebuildInvoice();
	})();
	</script>
	<?php return ob_get_clean();
});

/* AR Form (10 targets) */
add_shortcode('msp_ar_form', function () {
	msp_redirect_to_login();
	$card_id = intval($_GET['card_id'] ?? 0);
	$stamp_preview = isset($_GET['stamp_preview']) ? esc_url_raw($_GET['stamp_preview']) : '';
	if ($card_id && get_post_type($card_id) !== 'ecard_digital_card') $card_id = 0;
	ob_start(); ?>
	<div style="max-width:1100px;margin:32px auto;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.1);padding:24px;position:relative;">
		<div style="display:flex;gap:16px;align-items:flex-start">
			<div style="flex:1;min-width:0">
				<h2 style="margin:0 0 12px;color:#111827;text-align:left;">Formulaire AR <?php echo $card_id? 'pour la carte #'.$card_id:''; ?></h2>
				<p style="margin:0 0 16px;color:#6b7280;">Jusqu'à 10 cibles. Pour chaque cible: 4 vidéos (DE, FR, EN, ES)، modèle 3D (.glb)، musique، lien Business et Google Form (optionnel).</p>

				<form id="mspAr10Form" method="post" enctype="multipart/form-data" style="display:grid;gap:16px;">
					<?php wp_nonce_field('msp_ar10_nonce', 'msp_ar10_nonce'); ?>
					<input type="hidden" name="card_id" value="<?php echo esc_attr($card_id); ?>">

					<?php for ($i=1;$i<=10;$i++): $hidden = $i===1 ? '' : 'display:none;'; ?>
					<fieldset class="ar-target" data-index="<?php echo $i; ?>" style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;<?php echo $hidden; ?>">
						<legend style="padding:0 8px;color:#374151;font-weight:700">Cible <?php echo $i; ?></legend>
						<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Image cible <?php echo $i; ?> <?php echo $i===1 ? '(obligatoire)' : '(optionnel)'; ?></label>
								<input type="file" name="targets[<?php echo $i; ?>][image]" accept="image/*" <?php echo $i===1 ? 'required' : ''; ?> style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Modèle 3D (.glb)</label>
								<input type="file" name="targets[<?php echo $i; ?>][model]" accept=".glb" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
						</div>

						<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Vidéo (Allemand)</label>
								<input type="file" name="targets[<?php echo $i; ?>][video_de]" accept="video/*" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Vidéo (Français)</label>
								<input type="file" name="targets[<?php echo $i; ?>][video_fr]" accept="video/*" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
						</div>

						<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Vidéo (Anglais)</label>
								<input type="file" name="targets[<?php echo $i; ?>][video_en]" accept="video/*" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Vidéo (Espagnol)</label>
								<input type="file" name="targets[<?php echo $i; ?>][video_es]" accept="video/*" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
						</div>

						<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Musique (optionnel)</label>
								<input type="file" name="targets[<?php echo $i; ?>][audio]" accept="audio/*" style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Lien Google Form (optionnel)</label>
								<input type="url" name="targets[<?php echo $i; ?>][google_survey_url]" placeholder="https://forms.gle/..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
							</div>
						</div>

						<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
							<div>
								<label style="display:block;margin-bottom:6px;font-weight:600;color:#374151;">Lien Carte Business (optionnel)</label>
								<input type="url" name="targets[<?php echo $i; ?>][business_card_url]" placeholder="https://..." style="width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
								<a href="<?php echo esc_url(home_url('/digital-arcard')); ?>" target="_blank" style="display:inline-block;margin-top:6px;text-decoration:none;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;">Créer une carte business</a>
							</div>
						</div>
					</fieldset>
					<?php endfor; ?>

					<div style="display:flex;gap:10px;justify-content:center;">
						<button type="button" id="btnAddTarget" style="background:#111827;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;">+ Ajouter une cible</button>
						<button type="submit" style="background:#16a34a;color:#fff;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;">Valider</button>
					</div>
				</form>
			</div>

			<div style="width:320px;flex:0 0 320px;">
				<?php if ($stamp_preview): ?>
					<div style="position:sticky;top:10px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px">
						<div style="font-weight:700;margin-bottom:8px">Aperçu du tampon</div>
						<img src="<?php echo esc_url($stamp_preview); ?>" alt="" style="width:100%;height:auto;border-radius:10px;border:1px solid #e5e7eb">
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script>
	const sets = Array.from(document.querySelectorAll('.ar-target'));
	document.getElementById('btnAddTarget').addEventListener('click', ()=>{
		let hidden = null;
		for (let i=0;i<sets.length;i++){
			if (getComputedStyle(sets[i]).display==='none') { hidden = sets[i]; break; }
		}
		if (hidden) hidden.style.display = 'block';
		else alert('Maximum 10 cibles');
	});
	document.getElementById('mspAr10Form').addEventListener('submit', function(e){
		e.preventDefault();
		const fd = new FormData(this); fd.append('action','msp_create_ar_targets');
		fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method:'POST', body:fd })
		.then(async (r)=>{ const txt=await r.text(); let d=null; try{ d=JSON.parse(txt); }catch(e){} if(!r.ok||!d||!d.success){ throw new Error((d&&d.data)||txt||'Erreur'); } return d; })
		.then(d=>{ window.location.href = d.data.redirect_url; })
		.catch(err=>{ alert('Erreur AR: '+(err.message||err)); });
	});
	</script>
	<?php return ob_get_clean();
});
add_action('wp_ajax_msp_create_ar_targets', 'msp_create_ar_targets_handler');
function msp_create_ar_targets_handler(){
	if (!wp_verify_nonce($_POST['msp_ar10_nonce'] ?? '', 'msp_ar10_nonce')) wp_send_json_error('Erreur de sécurité');
	if (!msp_is_user_logged_in()) wp_send_json_error('Utilisateur non connecté');
	$card_id = intval($_POST['card_id'] ?? 0);
	if ($card_id && get_post_type($card_id) !== 'ecard_digital_card') $card_id = 0;
	$user_id = get_current_user_id();
	$ar_id = wp_insert_post([ 'post_title'=>'Expérience AR', 'post_content'=>'', 'post_status'=>'publish', 'post_type'=>'msp_ar_experience', 'post_author'=>$user_id ]);
	if (is_wp_error($ar_id)) wp_send_json_error('Erreur lors de la création AR');

	$targets = [];
	if (!empty($_FILES['targets']['name']) && is_array($_FILES['targets']['name'])) {
		for ($i=1;$i<=10;$i++){
			$slot = ['image'=>'','video_de'=>'','video_fr'=>'','video_en'=>'','video_es'=>'','model'=>'','audio'=>'','business_card_url'=>'','google_survey_url'=>''];
			$slot['business_card_url'] = isset($_POST['targets'][$i]['business_card_url']) ? esc_url_raw($_POST['targets'][$i]['business_card_url']) : '';
			$slot['google_survey_url'] = isset($_POST['targets'][$i]['google_survey_url']) ? esc_url_raw($_POST['targets'][$i]['google_survey_url']) : '';

			foreach (['image','video_de','video_fr','video_en','video_es','model','audio'] as $key) {
				if (!empty($_FILES['targets']['name'][$i][$key])) {
					$file = [
						'name'     => $_FILES['targets']['name'][$i][$key],
						'type'     => $_FILES['targets']['type'][$i][$key],
						'tmp_name' => $_FILES['targets']['tmp_name'][$i][$key],
						'error'    => $_FILES['targets']['error'][$i][$key],
						'size'     => $_FILES['targets']['size'][$i][$key],
					];
					$up = wp_handle_upload($file, ['test_form'=>false]);
					if (!isset($up['error'])) {
						if ($key==='model') $slot['model'] = $up['url'];
						elseif ($key==='audio') $slot['audio'] = $up['url'];
						else $slot[$key] = $up['url'];
					}
				}
			}
			if (!empty($slot['image']) || !empty($slot['business_card_url']) || !empty($slot['google_survey_url'])) $targets[$i] = $slot;
		}
	}
	if ($card_id) update_post_meta($ar_id, 'ar_card_id', $card_id);
	update_post_meta($ar_id, 'ar_targets', wp_json_encode($targets));
	update_post_meta($ar_id, 'ar_user_id', $user_id);
	$redirect = $card_id ? (msp_get_page_url_by_slug('card-preview') . '?card_id=' . $card_id) : msp_get_page_url_by_slug('dashboard');
	wp_send_json_success(['redirect_url'=>$redirect]);
}

/* Card Preview */
add_shortcode('msp_card_preview', function(){
	msp_redirect_to_login();
	$card_id = intval($_GET['card_id'] ?? 0);
	if (!$card_id || get_post_type($card_id) !== 'ecard_digital_card') {
		return '<div style="max-width:800px;margin:24px auto;background:#fff;border-radius:12px;padding:24px;">Carte invalide.</div>';
	}
	$design = get_post_meta($card_id,'dc_design_json',true);
	$name   = get_post_meta($card_id,'dc_name',true);
	$title  = get_post_meta($card_id,'dc_title',true);
	$phone  = get_post_meta($card_id,'dc_phone',true);
	$email  = get_post_meta($card_id,'dc_email',true);
	$addr   = get_post_meta($card_id,'dc_address',true);
	$site   = get_post_meta($card_id,'dc_website',true);
	$linkedin = get_post_meta($card_id,'dc_linkedin',true);
	$personal = get_post_meta($card_id,'dc_personal_photo',true);
	$companyLogo = get_post_meta($card_id,'dc_company_logo',true);
	
	// Parse design data
	$designData = json_decode($design, true);
	$theme = $designData['theme'] ?? 'flag';
	$orient = $designData['orient'] ?? 'v';
	
	ob_start(); ?>
	<style>
	/* ====== THEME 1: DRAPEAU FR ====== */
	.theme-flag{background:#0f214a;color:#fff;border:1px solid #0d1b3a}
	.theme-flag .flagbar{height:72px;display:flex}
	.theme-flag .fr-b{flex:1;background:#002395}
	.theme-flag .fr-w{flex:1;background:#fff}
	.theme-flag .fr-r{flex:1;background:#ED2939}
	.theme-flag .avatar-ring{width:104px;height:104px;border-radius:50%;
	  border:6px solid #fff;box-shadow:0 0 0 4px #ED2939,0 0 0 8px #002395;
	  background:#fff;overflow:hidden;position:absolute;left:50%;transform:translateX(-50%);top:38px}
	.theme-flag .thumb img{width:100%;height:100%;object-fit:cover}
	.theme-flag .hts-btns a{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02))}
	.theme-flag .meta{font-weight:600}

	/* ====== THEME 2: LUXE NAVY/OR ====== */
	.theme-luxe{background:#0a1020;color:#F5F5F5;border:1px solid rgba(255,215,0,.28)}
	.theme-luxe:before{content:"";position:absolute;inset:0;border-radius:22px;box-shadow:inset 0 0 0 2px rgba(255,215,0,.38);pointer-events:none}
	.theme-luxe .avatar-gold{width:120px;height:120px;border-radius:50%;border:5px solid #ffd700;
	  box-shadow:0 0 0 3px rgba(255,215,0,.35),0 10px 25px rgba(0,0,0,.25);overflow:hidden;margin:6px auto 12px;background:#fff}
	.theme-luxe .gold-line{height:1px;background:linear-gradient(90deg,#b38b00,#ffd700,#b38b00);opacity:.9;margin:8px 0 12px}
	.theme-luxe .hts-btns a{border-color:rgba(255,215,0,.35);background:linear-gradient(180deg,rgba(255,215,0,.10),rgba(8,8,8,.0));color:#fff}

	/* ====== THEME 3: BLEU MINIMAL + PLAYER ====== */
	.theme-min{background:#183788;color:#fff;border:1px solid #142f74}
	.theme-min .player{border:2px solid #fff;border-radius:16px;padding:16px;margin-top:12px;background:rgba(255,255,255,.06)}
	.theme-min .playbtn{width:66px;height:66px;border-radius:50%;border:3px solid #fff;display:flex;justify-content:center;align-items:center;margin:0 auto 10px}
	.theme-min .playbtn:after{content:"";border-left:18px solid #fff;border-top:12px solid transparent;border-bottom:12px solid transparent;margin-left:6px}
	.theme-min .progress{height:6px;border-radius:6px;background:#fff;opacity:.5;margin-top:8px}
	.theme-min .progress .dot{width:14px;height:14px;border-radius:50%;background:#ff3344;position:relative;top:-4px;left:35%}
	.theme-min .hts-btns a{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02))}

	/* کلاس‌های پایه */
	.hts-card{margin:18px auto;border:1px solid #E6E6E6;border-radius:22px;padding:16px;max-width:440px;position:relative;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.06)}
	.hts-card h2{margin:6px 0 4px;font-weight:800;letter-spacing:.2px}
	.hts-card .meta{opacity:.9;margin-bottom:12px}
	.hts-btns a{display:block;margin:8px 0;padding:12px 14px;border:1px solid rgba(255,255,255,.28);border-radius:12px;text-align:center;text-decoration:none;transition:.2s}
	.hts-btns a:hover{transform:translateY(-1px)}
	
	/* ====== Social row ====== */
	.icon-row{display:flex;gap:14px;margin-top:10px;align-items:center}
	.icon-row a{text-decoration:none;opacity:.95}
	.social-tag{font-size:12px;opacity:.85}
	</style>
	
	<div style="max-width:1100px;margin:24px auto;background:#fff;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.08);padding:16px;position:relative;">
		<a href="<?php echo esc_url(msp_get_page_url_by_slug('dashboard')); ?>" style="position:absolute;right:16px;top:16px;background:#111827;color:#fff;border-radius:8px;padding:8px 12px;text-decoration:none;">Tableau de bord</a>

		<h2 style="margin:0 0 10px;">Aperçu de la carte (depuis votre design)</h2>
		<p style="margin:0 0 12px;color:#6b7280">Cet aperçu ne modifie pas le thème public.</p>

		<div id="cardCanvas" class="hts-card <?php echo $orient==='h'?'hts-horiz':''; ?> theme-<?php echo esc_attr($theme); ?>" style="width:100%;max-width:520px;aspect-ratio:1.586;border:1px dashed #e5e7eb;border-radius:12px;position:relative;padding:0;background:#f9fafb;overflow:hidden;margin:0 auto;">
			<div id="cardInner" class="c-content theme-<?php echo esc_attr($theme); ?>" style="position:relative;width:100%;height:100%;border-radius:12px;overflow:hidden;">
				<?php if ($theme === 'flag'): ?>
					<div class="flagbar" style="height:72px;display:flex;">
						<div class="fr-b" style="flex:1;background:#002395;"></div>
						<div class="fr-w" style="flex:1;background:#fff;"></div>
						<div class="fr-r" style="flex:1;background:#ED2939;"></div>
					</div>
					<div class="avatar-ring" style="width:104px;height:104px;border-radius:50%;border:6px solid #fff;box-shadow:0 0 0 4px #ED2939,0 0 0 8px #002395;background:#fff;overflow:hidden;position:absolute;left:50%;transform:translateX(-50%);top:38px;">
						<?php if ($personal): ?>
							<img src="<?php echo esc_url($personal); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
						<?php endif; ?>
					</div>
					<div style="height:60px;"></div>
				<?php elseif ($theme === 'luxe'): ?>
					<div class="avatar-gold" style="width:120px;height:120px;border-radius:50%;border:5px solid #ffd700;box-shadow:0 0 0 3px rgba(255,215,0,.35),0 10px 25px rgba(0,0,0,.25);overflow:hidden;margin:6px auto 12px;background:#fff;">
						<?php if ($personal): ?>
							<img src="<?php echo esc_url($personal); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="body" style="padding:14px;">
					<h2 style="margin:6px 0 4px;font-weight:800;letter-spacing:.2px;color:inherit;"><?php echo esc_html($name ?: ''); ?></h2>
					<div class="meta" style="opacity:.9;margin-bottom:12px;color:inherit;"><?php echo esc_html($title); ?></div>
					
					<?php if ($theme === 'luxe'): ?>
						<div class="gold-line" style="height:1px;background:linear-gradient(90deg,#b38b00,#ffd700,#b38b00);opacity:.9;margin:8px 0 12px;"></div>
					<?php endif; ?>

					<div class="hts-btns">
						<?php if ($phone): ?>
							<a href="tel:<?php echo esc_attr($phone); ?>" style="display:block;margin:8px 0;padding:12px 14px;border:1px solid rgba(255,255,255,.28);border-radius:12px;text-align:center;text-decoration:none;transition:.2s;color:inherit;">📞 Appeler</a>
						<?php endif; ?>
						<?php if ($email): ?>
							<a href="mailto:<?php echo esc_attr($email); ?>" style="display:block;margin:8px 0;padding:12px 14px;border:1px solid rgba(255,255,255,.28);border-radius:12px;text-align:center;text-decoration:none;transition:.2s;color:inherit;">✉️ E-mail</a>
						<?php endif; ?>
						<?php if ($site): ?>
							<a href="<?php echo esc_url($site); ?>" target="_blank" style="display:block;margin:8px 0;padding:12px 14px;border:1px solid rgba(255,255,255,.28);border-radius:12px;text-align:center;text-decoration:none;transition:.2s;color:inherit;">🌐 Site web</a>
						<?php endif; ?>
					</div>

					<div class="icon-row" style="display:flex;gap:14px;margin-top:10px;align-items:center;">
						<?php if ($linkedin): ?>
							<a href="<?php echo esc_url($linkedin); ?>" target="_blank" style="text-decoration:none;opacity:.95;">in <span class="social-tag" style="font-size:12px;opacity:.85;">LinkedIn</span></a>
						<?php endif; ?>
					</div>

					<?php if ($theme === 'min'): ?>
						<div class="player" style="border:2px solid #fff;border-radius:16px;padding:16px;margin-top:12px;background:rgba(255,255,255,.06);">
							<div class="playbtn" style="width:66px;height:66px;border-radius:50%;border:3px solid #fff;display:flex;justify-content:center;align-items:center;margin:0 auto 10px;"></div>
							<div class="progress" style="height:6px;border-radius:6px;background:#fff;opacity:.5;margin-top:8px;">
								<div class="dot" style="width:14px;height:14px;border-radius:50%;background:#ff3344;position:relative;top:-4px;left:35%;"></div>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<script>
	(function(){
		const design = (function(){ try { return JSON.parse(<?php echo json_encode($design ? $design : 'null'); ?>); } catch(e){ return null; } })();
		function renderDesign(){
			const inner = document.getElementById('cardInner');
			if(!inner) return;
			
			// Apply theme-specific styles
			const theme = '<?php echo esc_js($theme); ?>';
			const orient = '<?php echo esc_js($orient); ?>';
			
			// Update theme classes
			inner.className = 'c-content theme-' + theme;
			if (orient === 'h') {
				inner.parentElement.classList.add('hts-horiz');
			}
			
			// Apply theme-specific background colors
			if (theme === 'flag') {
				inner.style.background = '#0f214a';
				inner.style.color = '#fff';
			} else if (theme === 'luxe') {
				inner.style.background = '#0a1020';
				inner.style.color = '#F5F5F5';
			} else if (theme === 'min') {
				inner.style.background = '#183788';
				inner.style.color = '#fff';
			}
			
			// Render design elements if available
			if (design && Array.isArray(design.textLines)){
				design.textLines.forEach((txt, idx)=>{
					const el = document.createElement('div');
					el.textContent = (txt||'').trim() || ' ';
					el.style.position='absolute'; 
					el.style.left='14px'; 
					el.style.top=(10+idx*28)+'px';
					el.style.font='600 18px system-ui';
					el.style.color = inner.style.color || '#fff';
					inner.appendChild(el);
				});
			}
			
			if (design && design.logo){
				const logo = document.createElement('div');
				logo.style.position='absolute'; 
				logo.style.left='40px'; 
				logo.style.top='40px'; 
				logo.style.width='84px'; 
				logo.style.height='84px';
				logo.style.backgroundImage = `url(${design.logo})`; 
				logo.style.backgroundSize='contain'; 
				logo.style.backgroundRepeat='no-repeat'; 
				logo.style.backgroundPosition='center';
				inner.appendChild(logo);
			}
		}
		renderDesign();
	})();
	</script>
	<?php return ob_get_clean();
});

/* Public Card View - FULLSCREEN */
add_action('template_redirect', function(){
	if (is_page() && get_queried_object() && get_queried_object()->post_name === 'digital-card' && isset($_GET['view'])) {
		$cid = intval($_GET['view']);
		wp_redirect(msp_get_page_url_by_slug('card-view').'?card_id='.$cid);
		exit;
	}
	if (is_page() && get_queried_object() && get_queried_object()->post_name === 'card-view') {
		msp_render_full_card_view();
		exit;
	}
});
add_action('wp_ajax_msp_set_theme', function () {
	if (!is_user_logged_in()) wp_send_json_error(['message'=>'Non autorisé'], 403);
	$card_id = isset($_POST['card_id']) ? intval($_POST['card_id']) : 0;
	$theme = isset($_POST['theme']) ? sanitize_text_field($_POST['theme']) : '';
	$nonce = isset($_POST['security']) ? $_POST['security'] : '';
	if (!$card_id || !wp_verify_nonce($nonce, 'msp_set_theme_'.$card_id)) wp_send_json_error(['message'=>'Requête invalide'], 400);
	$author_id = intval(get_post_field('post_author', $card_id));
	if ($author_id !== get_current_user_id()) wp_send_json_error(['message'=>'Accès refusé'], 403);
	$themes = msp_valid_themes();
	if (!isset($themes[$theme])) wp_send_json_error(['message'=>'Thème invalide'], 400);
	update_post_meta($card_id, 'msp_theme', $theme);
	wp_send_json_success(['ok'=>true]);
});
function msp_render_full_card_view() {
	$card_id = intval($_GET['card_id'] ?? 0);
	$card = get_post($card_id);
	if (!$card || $card->post_type !== 'ecard_digital_card') {
		status_header(404);
		echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Carte introuvable</title></head><body style="margin:0;font-family:system-ui,Segoe UI,Arial"><div style="display:flex;align-items:center;justify-content:center;height:100vh;">Carte introuvable</div></body></html>';
		return;
	}
	$owner_id = (int) $card->post_author;
	$name   = get_post_meta($card_id,'dc_name',true);
	$title  = get_post_meta($card_id,'dc_title',true);
	$phone  = get_post_meta($card_id,'dc_phone',true);
	$email  = get_post_meta($card_id,'dc_email',true);
	$addr   = get_post_meta($card_id,'dc_address',true);
	$site   = get_post_meta($card_id,'dc_website',true);
	$linkedin = get_post_meta($card_id,'dc_linkedin',true);
	$personal = get_post_meta($card_id,'dc_personal_photo',true);
	$companyLogo = get_post_meta($card_id,'dc_company_logo',true);

	$stored_theme = get_post_meta($card_id, 'msp_theme', true);
	if (!$stored_theme) $stored_theme = 'flag';
	$is_owner = is_user_logged_in() && (int)get_current_user_id()===$owner_id;
	$theme = $stored_theme;
	$themes = msp_valid_themes();
	$nonce = wp_create_nonce('msp_set_theme_'.$card_id);

	header_remove('Link');
	nocache_headers();
	header('Content-Type: text/html; charset=utf-8');
	?>
	<!doctype html>
	<html lang="fr">
	<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
	<title><?php echo esc_html($name ?: 'Carte digitale'); ?></title>
	<style>
		html,body{height:100%;margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial}
		body{background:var(--bg,#0b0b0b);color:#fff;overflow:hidden}
		
		/* Theme-specific backgrounds */
		.theme-flag{ --bg:#0f214a; }
		.theme-luxe{ --bg:#0a1020; }
		.theme-min{ --bg:#183788; }
		.theme-classique{ --bg:#0b0b0b; }
		.theme-verre{ --bg:#0a0c10; }
		.theme-ombre{ --bg:#0d0d0d; }
		.theme-neon{ --bg:#1a0b22; }
		.theme-minimal{ --bg:#121212; }
		.theme-carte{ --bg:#0a0e16; }
		.theme-moderne{ --bg:#0a0b12; }
		.theme-degrade{ --bg:#0b1118; }
		.theme-sombre{ --bg:#0b0f19; }
		.theme-clair{ --bg:#e9edf5; color:#111; }
		.theme-corporate{ --bg:#0a1633; }
		.theme-pastel{ --bg:#f6efe3; color:#111; }

		.wrap{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;background:var(--bg,#000);color:currentColor;padding:20px}
		
		/* Card styles based on theme */
		.card-container{max-width:440px;width:100%;margin:0 auto;border-radius:22px;padding:16px;position:relative;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,.06)}
		
		/* Flag theme */
		.theme-flag .card-container{border:1px solid #E6E6E6;background:#0f214a;color:#fff}
		.theme-flag .flagbar{height:72px;display:flex;margin-bottom:60px}
		.theme-flag .fr-b{flex:1;background:#002395}
		.theme-flag .fr-w{flex:1;background:#fff}
		.theme-flag .fr-r{flex:1;background:#ED2939}
		.theme-flag .avatar-ring{width:104px;height:104px;border-radius:50%;border:6px solid #fff;box-shadow:0 0 0 4px #ED2939,0 0 0 8px #002395;background:#fff;overflow:hidden;position:absolute;left:50%;transform:translateX(-50%);top:38px}
		.theme-flag .card-content{text-align:center;margin-top:60px}
		.theme-flag .card-buttons a{display:block;padding:12px 14px;border:1px solid rgba(255,255,255,.28);border-radius:12px;text-align:center;text-decoration:none;background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02));margin:8px 0;transition:.2s}
		.theme-flag .card-buttons a:hover{transform:translateY(-1px)}
		
		/* Luxe theme */
		.theme-luxe .card-container{border:1px solid rgba(255,215,0,.28);background:#0a1020;color:#F5F5F5}
		.theme-luxe .card-container:before{content:"";position:absolute;inset:0;border-radius:22px;box-shadow:inset 0 0 0 2px rgba(255,215,0,.38);pointer-events:none}
		.theme-luxe .avatar-gold{width:120px;height:120px;border-radius:50%;border:5px solid #ffd700;box-shadow:0 0 0 3px rgba(255,215,0,.35),0 10px 25px rgba(0,0,0,.25);overflow:hidden;margin:6px auto 12px;background:#fff}
		.theme-luxe .card-content{text-align:center}
		.theme-luxe .gold-line{height:1px;background:linear-gradient(90deg,#b38b00,#ffd700,#b38b00);opacity:.9;margin:8px 0 12px}
		.theme-luxe .card-buttons a{display:block;padding:12px 14px;border:1px solid rgba(255,215,0,.35);border-radius:12px;text-align:center;text-decoration:none;background:linear-gradient(180deg,rgba(255,215,0,.10),rgba(8,8,8,.0));color:#fff;margin:8px 0;transition:.2s}
		.theme-luxe .card-buttons a:hover{transform:translateY(-1px)}
		
		/* Min theme */
		.theme-min .card-container{border:1px solid #142f74;background:#183788;color:#fff}
		.theme-min .card-content{text-align:center}
		.theme-min .card-buttons a{display:block;padding:12px 14px;border:1px solid rgba(255,255,255,.28);border-radius:12px;text-align:center;text-decoration:none;background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.02));margin:8px 0;transition:.2s}
		.theme-min .card-buttons a:hover{transform:translateY(-1px)}
		.theme-min .player{border:2px solid #fff;border-radius:16px;padding:16px;margin-top:12px;background:rgba(255,255,255,.06)}
		.theme-min .playbtn{width:66px;height:66px;border-radius:50%;border:3px solid #fff;display:flex;justify-content:center;align-items:center;margin:0 auto 10px}
		.theme-min .playbtn:after{content:"";border-left:18px solid #fff;border-top:12px solid transparent;border-bottom:12px solid transparent;margin-left:6px}
		.theme-min .progress{height:6px;border-radius:6px;background:#fff;opacity:.5;margin-top:8px}
		.theme-min .progress .dot{width:14px;height:14px;border-radius:50%;background:#ff3344;position:relative;top:-4px;left:35%}
		
		/* Default theme styles */
		.theme-classique .card-container{background:rgba(20,20,20,.75);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.08);border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.6)}
		.theme-classique .card-content{text-align:center;padding:32px}
		.theme-classique .card-buttons a{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;color:inherit;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.06);transition:.2s}
		.theme-classique .card-buttons a:last-child{border-bottom:none}
		.theme-classique .card-buttons a:hover{background:rgba(255,255,255,.05)}
		
		/* Common styles */
		.card-title{margin:6px 0 4px;font-weight:800;letter-spacing:.2px;font-size:24px}
		.card-subtitle{opacity:.9;margin-bottom:12px;font-weight:600}
		.card-buttons{display:flex;flex-direction:column;gap:8px}
		
		/* ====== Social row ====== */
		.icon-row{display:flex;gap:14px;margin-top:10px;align-items:center}
		.icon-row a{text-decoration:none;opacity:.95}
		.social-tag{font-size:12px;opacity:.85}
		
		/* Theme selector */
		.theme-panel{position:fixed;right:16px;bottom:16px;z-index:999999;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.2);border-radius:12px;padding:10px;backdrop-filter:blur(8px);max-height:70vh;overflow:auto}
		.theme-chip{display:block;margin:6px 0;padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.25);color:#fff;text-decoration:none;cursor:pointer;font-size:13px;white-space:nowrap}
		.theme-chip.active{background:#fff;color:#000;border-color:#fff}
		
		/* Dashboard button */
		.dash-btn{position:fixed;left:16px;bottom:16px;z-index:999999;background:rgba(0,0,0,.6);color:#fff;text-decoration:none;padding:10px 14px;border-radius:999px;border:1px solid rgba(255,255,255,.25);backdrop-filter: blur(8px)}
		
		@media (max-width:640px){
			.wrap{padding:10px}
			.card-container{max-width:100%}
		}
	</style>
	</head>
	<body class="theme-<?php echo esc_attr($theme); ?>">
	<div class="wrap">
		<div class="card-container">
			<?php if($theme==='flag'): ?>
				<div class="flagbar">
					<div class="fr-b"></div>
					<div class="fr-w"></div>
					<div class="fr-r"></div>
				</div>
				<?php if ($personal): ?>
				<div class="avatar-ring">
					<img src="<?php echo esc_url($personal); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
				</div>
				<?php endif; ?>
				<div class="card-content">
					<h2 class="card-title"><?php echo esc_html($name ?: ''); ?></h2>
					<div class="card-subtitle"><?php echo esc_html($title); ?></div>
					<div class="card-buttons">
						<?php if ($phone): ?><a href="tel:<?php echo esc_attr($phone); ?>">📞 Appeler</a><?php endif; ?>
						<?php if ($email): ?><a href="mailto:<?php echo esc_attr($email); ?>">✉️ E-mail</a><?php endif; ?>
						<?php if ($site): ?><a href="<?php echo esc_url($site); ?>" target="_blank" rel="noopener">🌐 Site web</a><?php endif; ?>
					</div>
					<div class="icon-row">
						<?php if ($linkedin): ?><a href="<?php echo esc_url($linkedin); ?>" target="_blank">in <span class="social-tag">LinkedIn</span></a><?php endif; ?>
					</div>
				</div>
			<?php elseif($theme==='luxe'): ?>
				<?php if ($personal): ?>
				<div class="avatar-gold">
					<img src="<?php echo esc_url($personal); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
				</div>
				<?php endif; ?>
				<div class="card-content">
					<h2 class="card-title"><?php echo esc_html($name ?: ''); ?></h2>
					<div class="card-subtitle"><?php echo esc_html($title); ?></div>
					<div class="gold-line"></div>
					<div class="card-buttons">
						<?php if ($phone): ?><a href="tel:<?php echo esc_attr($phone); ?>">📞 Appeler</a><?php endif; ?>
						<?php if ($email): ?><a href="mailto:<?php echo esc_attr($email); ?>">✉️ E-mail</a><?php endif; ?>
						<?php if ($site): ?><a href="<?php echo esc_url($site); ?>" target="_blank" rel="noopener">🌐 Site web</a><?php endif; ?>
					</div>
					<div class="icon-row">
						<?php if ($linkedin): ?><a href="<?php echo esc_url($linkedin); ?>" target="_blank">in <span class="social-tag">LinkedIn</span></a><?php endif; ?>
					</div>
				</div>
			<?php elseif($theme==='min'): ?>
				<div class="card-content">
					<h2 class="card-title"><?php echo esc_html($name ?: ''); ?></h2>
					<div class="card-subtitle"><?php echo esc_html($title); ?></div>
					<div class="card-buttons">
						<?php if ($phone): ?><a href="tel:<?php echo esc_attr($phone); ?>">📞 Appeler</a><?php endif; ?>
						<?php if ($email): ?><a href="mailto:<?php echo esc_attr($email); ?>">✉️ E-mail</a><?php endif; ?>
						<?php if ($site): ?><a href="<?php echo esc_url($site); ?>" target="_blank" rel="noopener">🌐 Site web</a><?php endif; ?>
					</div>
					<div class="icon-row">
						<?php if ($linkedin): ?><a href="<?php echo esc_url($linkedin); ?>" target="_blank">in <span class="social-tag">LinkedIn</span></a><?php endif; ?>
					</div>
					<div class="player">
						<div class="playbtn"></div>
						<div class="progress"><div class="dot"></div></div>
					</div>
				</div>
			<?php else: ?>
				<!-- Default theme -->
				<div class="card-content">
					<h2 class="card-title"><?php echo esc_html($name ?: ''); ?></h2>
					<div class="card-subtitle"><?php echo esc_html($title); ?></div>
					<div class="card-buttons">
						<?php if ($phone): ?><a href="tel:<?php echo esc_attr($phone); ?>"><span>Téléphone</span><strong><?php echo esc_html($phone); ?></strong></a><?php endif; ?>
						<?php if ($email): ?><a href="mailto:<?php echo esc_attr($email); ?>"><span>E-mail</span><strong><?php echo esc_html($email); ?></strong></a><?php endif; ?>
						<?php if ($site): ?><a href="<?php echo esc_url($site); ?>" target="_blank" rel="noopener"><span>Site web</span><strong><?php echo esc_html(parse_url($site, PHP_URL_HOST) ?: $site); ?></strong></a><?php endif; ?>
						<?php if ($linkedin): ?><a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener"><span>Réseau</span><strong>LinkedIn</strong></a><?php endif; ?>
						<?php if ($addr): ?><a><span>Adresse</span><strong><?php echo esc_html($addr); ?></strong></a><?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	
	<a class="dash-btn" href="<?php echo esc_url(msp_get_page_url_by_slug('dashboard')); ?>">Tableau de bord</a>

	<?php if ($is_owner): ?>
	<div class="theme-panel">
		<div style="font-weight:700;margin-bottom:6px">Thème</div>
		<?php foreach ($themes as $k=>$label): ?>
			<a href="#" class="theme-chip <?php echo $k===$theme?'active':''; ?>" data-theme="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></a>
		<?php endforeach; ?>
	</div>
	<script>
	(function(){
		const chips=document.querySelectorAll(".theme-chip");
		chips.forEach(c=>{
			c.addEventListener("click",function(e){
				e.preventDefault();
				const th=this.getAttribute("data-theme");
				const fd=new FormData();
				fd.append("action","msp_set_theme");
				fd.append("card_id","<?php echo esc_attr($card_id); ?>");
				fd.append("theme",th);
				fd.append("security","<?php echo esc_attr($nonce); ?>");
				fetch("<?php echo esc_url(admin_url('admin-ajax.php')); ?>",{method:"POST",body:fd,credentials:"same-origin"})
				.then(r=>r.json()).then(j=>{ if(j&&j.success){ window.location.reload(); } else { alert("Erreur de thème"); } })
				.catch(()=>alert("Erreur réseau"));
			});
		});
	})();
	</script>
	<?php endif; ?>

	<script>
	(function(){
		try {
			const fd=new FormData(); fd.append('action','msp_track_view'); fd.append('card_id','<?php echo (int)$card_id; ?>');
			navigator.sendBeacon && navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', fd);
		}catch(e){}
	})();
	</script>
	</body>
	</html>
	<?php
}

/* Downloads & Tracking */
add_action('wp_ajax_msp_download_qr', 'msp_download_qr_handler');
add_action('wp_ajax_nopriv_msp_download_qr', 'msp_download_qr_handler');
function msp_download_qr_handler() {
	if (!wp_verify_nonce($_GET['nonce'] ?? '', 'msp_download_qr')) wp_die('Erreur de sécurité');
	$card_id = intval($_GET['card_id'] ?? 0); $card = get_post($card_id);
	if (!$card || $card->post_type !== 'ecard_digital_card') wp_die('Carte non trouvée');
	$card_url = msp_get_page_url_by_slug('card-view') . '?card_id=' . $card_id;
	$qr_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($card_url);
	if (msp_is_user_logged_in()) msp_track_card_action($card_id, get_current_user_id(), 'qr_downloaded');
	wp_redirect($qr_url); exit;
}
add_action('wp_ajax_msp_download_vcard', 'msp_download_vcard_handler');
add_action('wp_ajax_nopriv_msp_download_vcard', 'msp_download_vcard_handler');
function msp_download_vcard_handler() {
	if (!wp_verify_nonce($_GET['nonce'] ?? '', 'msp_download_vcard')) wp_die('Erreur de sécurité');
	$card_id = intval($_GET['card_id'] ?? 0); $card = get_post($card_id);
	if (!$card || $card->post_type !== 'ecard_digital_card') wp_die('Carte non trouvée');
	$name = get_post_meta($card_id, 'dc_name', true); $title = get_post_meta($card_id, 'dc_title', true);
	$phone = get_post_meta($card_id, 'dc_phone', true); $email = get_post_meta($card_id, 'dc_email', true);
	$address = get_post_meta($card_id, 'dc_address', true); $website = get_post_meta($card_id, 'dc_website', true);
	$vcard = "BEGIN:VCARD\r\n"."VERSION:3.0\r\n"."FN:".$name."\r\n"."TITLE:".$title."\r\n"."TEL:".$phone."\r\n"."EMAIL:".$email."\r\n";
	if ($address) $vcard .= "ADR:;;".$address.";;\r\n";
	if ($website) $vcard .= "URL:".$website."\r\n";
	$vcard .= "END:VCARD\r\n";
	if (msp_is_user_logged_in()) msp_track_card_action($card_id, $card->post_author, 'vcard_downloaded');
	header('Content-Type: text/vcard'); header('Content-Disposition: attachment; filename="'.sanitize_file_name($name).'.vcf"'); echo $vcard; exit;
}
add_action('wp_ajax_msp_track_view', 'msp_track_view_handler');
add_action('wp_ajax_nopriv_msp_track_view', 'msp_track_view_handler');
function msp_track_view_handler() {
	$card_id = intval($_POST['card_id'] ?? 0); $card = get_post($card_id);
	if (!$card || $card->post_type !== 'ecard_digital_card') wp_send_json_error('Carte non trouvée');
	msp_track_card_action($card_id, $card->post_author, 'card_viewed'); wp_send_json_success();
}
add_action('wp_ajax_msp_track_click', 'msp_track_click_handler');
add_action('wp_ajax_nopriv_msp_track_click', 'msp_track_click_handler');
function msp_track_click_handler() {
	$card_id = intval($_POST['card_id'] ?? 0); $action_type = sanitize_text_field($_POST['action_type'] ?? '');
	$card = get_post($card_id); if (!$card || $card->post_type !== 'ecard_digital_card') wp_send_json_error('Carte non trouvée');
	msp_track_card_action($card_id, $card->post_author, $action_type); wp_send_json_success();
}
function msp_track_card_action($card_id, $user_id, $action_type, $action_data = '') {
	global $wpdb; $analytics_table = $wpdb->prefix . 'digital_card_analytics';
	$wpdb->insert($analytics_table, [
		'card_id' => $card_id, 'user_id' => $user_id, 'action_type' => $action_type,
		'action_data' => $action_data, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
	]);
}

/* Admin overview */
function msp_admin_dashboard_page() {
	global $wpdb;
	$total_cards = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'ecard_digital_card' AND post_status = 'publish'");
	$total_ar = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'msp_ar_experience' AND post_status = 'publish'");
	$total_leads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}digital_card_leads");
	$total_views = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}digital_card_analytics WHERE action_type = 'card_viewed'");
	echo '<div class="wrap"><h1>Tableau de bord MSP</h1><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin:20px 0;">
	<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"><h3>Cartes Digitales</h3><p style="font-size:24px;font-weight:bold;color:#2563eb;">'.$total_cards.'</p></div>
	<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"><h3>Expériences AR</h3><p style="font-size:24px;font-weight:bold;color:#10b981;">'.$total_ar.'</p></div>
	<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"><h3>Contacts</h3><p style="font-size:24px;font-weight:bold;color:#f59e0b;">'.$total_leads.'</p></div>
	<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);"><h3>Vues</h3><p style="font-size:24px;font-weight:bold;color:#ef4444;">'.$total_views.'</p></div>
	</div></div>';
}

/* Chat core */
add_shortcode('msp_chat', function(){
	msp_redirect_to_login();
	$user_id = get_current_user_id();
	$thread_id = msp_chat_get_or_create_thread($user_id);
	ob_start(); ?>
	<div style="max-width:900px;margin:24px auto;background:#fff;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,.06);padding:16px;">
		<h2 style="margin:0 0 12px;">Chat avec le support</h2>
		<div id="chatBox" style="height:360px;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:8px;"></div>
		<form id="chatForm" style="display:flex;gap:8px;">
			<?php wp_nonce_field('msp_chat_nonce','msp_chat_nonce'); ?>
			<input type="hidden" name="thread_id" value="<?php echo esc_attr($thread_id); ?>">
			<input type="text" name="message" placeholder="Votre message..." style="flex:1;padding:10px;border:1px solid #d1d5db;border-radius:8px;">
			<button class="button button-primary" type="submit" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:10px 16px;">Envoyer</button>
		</form>
	</div>
	<script>
	const box = document.getElementById('chatBox'); const form= document.getElementById('chatForm');
	function render(items){
		box.innerHTML = (items||[]).map(it=>{
			const side = it.sender==='user'?'flex-end':'flex-start';
			const bg   = it.sender==='user'?'#dbeafe':(it.sender==='bot'?'#dcfce7':'#f3f4f6');
			return `<div style="display:flex;justify-content:${side};margin:6px 0"><div style="max-width:70%;background:${bg};border-radius:10px;padding:8px 10px;">${it.message}</div></div>`;
		}).join('');
		box.scrollTop = box.scrollHeight;
	}
	function poll(){ fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=msp_chat_poll&thread_id=<?php echo $thread_id; ?>').then(r=>r.json()).then(d=>{ if(d.success) render(d.data); }); }
	setInterval(poll, 3000); poll();
	form.addEventListener('submit', function(e){
		e.preventDefault(); const fd = new FormData(form); fd.append('action','msp_chat_send_user');
		fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method:'POST', body:fd })
		.then(r=>r.json()).then(d=>{ if(d.success){ form.message.value=''; poll(); }});
	});
	</script>
	<?php return ob_get_clean();
});
add_action('wp_ajax_msp_chat_poll', function(){
	$thread_id = intval($_GET['thread_id'] ?? 0); if (!$thread_id) wp_send_json_error();
	global $wpdb; $m = $wpdb->prefix.'msp_chat_messages';
	$rows = $wpdb->get_results($wpdb->prepare("SELECT sender,message,created_at FROM $m WHERE thread_id=%d ORDER BY id ASC", $thread_id), ARRAY_A);
	wp_send_json_success($rows ?: []);
});
add_action('wp_ajax_msp_chat_send_user', function(){
	if (!wp_verify_nonce($_POST['msp_chat_nonce'] ?? '', 'msp_chat_nonce')) wp_send_json_error('security');
	if (!msp_is_user_logged_in()) wp_send_json_error('noauth');
	$thread_id = intval($_POST['thread_id'] ?? 0); $msg = wp_kses_post($_POST['message'] ?? '');
	if (!$thread_id || !$msg) wp_send_json_error('invalid');
	msp_chat_add_message($thread_id, 'user', $msg); wp_send_json_success();
});
function msp_chat_get_or_create_thread($user_id){
	global $wpdb; $t = $wpdb->prefix.'msp_chat_threads';
	$thread_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d LIMIT 1", $user_id));
	if ($thread_id) return intval($thread_id);
	$wpdb->insert($t, ['user_id'=>$user_id, 'created_at'=>current_time('mysql')]);
	return intval($wpdb->insert_id);
}
function msp_chat_add_message($thread_id, $sender, $message){
	global $wpdb; $m = $wpdb->prefix.'msp_chat_messages';
	$wpdb->insert($m, ['thread_id'=>$thread_id,'sender'=>$sender,'message'=>$message,'created_at'=>current_time('mysql')]);
	return intval($wpdb->insert_id);
}
function msp_chat_send_invoice_message($user_id, $card_id, $data=[]){
	$thread = msp_chat_get_or_create_thread($user_id);
	$msg = "Votre reçu a été enregistré.<br>Numéro de carte: #{$card_id}";
	if (!empty($data['targets'])) $msg .= "<br>Nombre de cibles AR: ".intval($data['targets']);
	msp_chat_add_message($thread, 'bot', $msg);
}

/* Floating chat widget */
add_action('wp_footer', function(){
	$logged = is_user_logged_in();
	$login_url = msp_get_page_url_by_slug('login') . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
	?>
	<div id="mspChatFab" style="position:fixed;right:16px;bottom:16px;z-index:9999;">
		<button id="mspChatFabBtn" style="background:#2563eb;color:#fff;border:none;border-radius:999px;width:56px;height:56px;box-shadow:0 8px 20px rgba(37,99,235,.4);cursor:pointer;">💬</button>
		<div id="mspChatPanel" style="display:none;position:fixed;right:16px;bottom:80px;width:320px;max-height:60vh;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.15);overflow:hidden;z-index:9999;">
			<div style="background:#2563eb;color:#fff;padding:10px 12px;font-weight:700;display:flex;justify-content:space-between;align-items:center;">
				<span>Chat</span><button id="mspChatClose" style="background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;">×</button>
			</div>
			<div id="mspChatBody" style="height:280px;overflow:auto;padding:10px;"></div>
			<?php if ($logged): ?>
			<form id="mspChatSend" style="display:flex;gap:6px;padding:10px;border-top:1px solid #e5e7eb;">
				<?php wp_nonce_field('msp_chat_nonce','msp_chat_nonce'); ?>
				<input type="text" name="message" placeholder="Votre message..." style="flex:1;padding:8px;border:1px solid #d1d5db;border-radius:8px;">
				<button style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;cursor:pointer;">Envoyer</button>
			</form>
			<?php else: ?>
			<div style="padding:10px;border-top:1px solid #e5e7eb;text-align:center;"><a href="<?php echo esc_url($login_url); ?>">Se connecter pour chatter</a></div>
			<?php endif; ?>
		</div>
	</div>
	<script>
	(function(){
		const btn=document.getElementById('mspChatFabBtn'); const panel=document.getElementById('mspChatPanel'); const close=document.getElementById('mspChatClose');
		btn.addEventListener('click', ()=>{ panel.style.display = (panel.style.display==='block'?'none':'block'); if (panel.style.display==='block') poll(); });
		close.addEventListener('click', ()=> panel.style.display='none');
		<?php if ($logged): $thread = msp_chat_get_or_create_thread(get_current_user_id()); ?>
		const body=document.getElementById('mspChatBody'); function render(items){ body.innerHTML=(items||[]).map(it=>{ const side=it.sender==='user'?'flex-end':'flex-start'; const bg=it.sender==='user'?'#dbeafe':(it.sender==='bot'?'#dcfce7':'#f3f4f6'); return `<div style="display:flex;justify-content:${side};margin:6px 0"><div style="max-width:80%;background:${bg};border-radius:10px;padding:8px 10px;">${it.message}</div></div>`; }).join(''); body.scrollTop=body.scrollHeight; }
		function poll(){ fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=msp_chat_poll&thread_id=<?php echo $thread; ?>').then(r=>r.json()).then(d=>{ if(d.success) render(d.data); }); }
		setInterval(poll, 5000);
		document.getElementById('mspChatSend').addEventListener('submit', function(e){ e.preventDefault(); const fd=new FormData(this); fd.append('action','msp_chat_send_user'); fd.append('thread_id','<?php echo $thread; ?>'); fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success){ this.message.value=''; poll(); } }); });
		<?php endif; ?>
	})();
	</script>
	<?php
});

/* Floating dashboard button */
add_action('wp_footer', function () {
	$dash_url = msp_get_page_url_by_slug('dashboard');
	$login_url = msp_get_page_url_by_slug('login');
	$href = is_user_logged_in() ? $dash_url : $login_url;
	echo '<div id="msp-floating-dash" style="position:fixed;right:16px;bottom:80px;z-index:9998;"><a href="' . esc_url($href) . '" style="display:inline-block;background:#111;color:#fff;padding:10px 14px;border-radius:999px;text-decoration:none;font-weight:600;box-shadow:0 6px 24px rgba(0,0,0,.25)">Tableau de bord</a></div>';
});

/* MIME Types */
add_filter('upload_mimes', function($mimes) {
	$mimes['vcf'] = 'text/vcard'; $mimes['glb'] = 'model/gltf-binary';
	return $mimes;
});

/* Classic editor for ar-stamp products */
add_filter('use_block_editor_for_post_type', function($use_block_editor, $post_type) {
	if ($post_type === 'product') {
		$product = wc_get_product(get_the_ID());
		if ($product && has_term('ar-stamp', 'product_cat', $product->get_id())) return false;
	}
	return $use_block_editor;
}, 10, 2);

/* Email helper */
function msp_send_simple_email($to, $subject, $message){
	if (!$to) return;
	$headers = ['Content-Type: text/html; charset=UTF-8'];
	wp_mail($to, $subject, wpautop($message), $headers);
}

/* Redirect ar-stamp product to customizer and loop button */
add_action('template_redirect', function() {
	if (function_exists('is_product') && is_product()) {
		$product = wc_get_product(get_the_ID());
		if ($product && has_term('ar-stamp', 'product_cat', $product->get_id())) {
			$customizer_url = msp_get_page_url_by_slug('stamp-customizer') . '?product_id=' . $product->get_id();
			wp_redirect($customizer_url);
			exit;
		}
	}
});
add_filter('woocommerce_loop_add_to_cart_link', function($button, $product) {
	if ($product && has_term('ar-stamp', 'product_cat', $product->get_id())) {
		$customizer_url = msp_get_page_url_by_slug('stamp-customizer') . '?product_id=' . $product->get_id();
		return '<a href="' . esc_url($customizer_url) . '" class="button">Personnaliser</a>';
	}
	return $button;
}, 10, 2);
