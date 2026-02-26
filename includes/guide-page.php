<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mira_ls_guide_page() {
	?>
	<div class="wrap" style="max-width:860px;">
		<h1>📖 Mira Language Switcher — Guide</h1>

		<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:24px 28px;margin-top:16px;">

			<h2 style="margin-top:0;">How It Works</h2>
			<p>The plugin serves your site under language-prefixed URLs: <code>/it/</code> for Italian, <code>/en/</code> for English. Visitors arriving at the root (<code>/</code>) are served the default language. A cookie (<code>mira_language</code>) remembers their last chosen language.</p>

			<hr>

			<h2>Initial Setup</h2>
			<ol>
				<li>Go to <strong>Mira Language → Setup</strong>, set the <strong>Default Language</strong> and tick the <strong>Enabled Languages</strong> you want to activate.</li>
				<li>Go to <strong>Mira Language → Settings</strong>.</li>
				<li>Under <em>Menu Integration</em>, tick <strong>Add to Menu</strong> — this appends flag icons to your nav menu automatically.</li>
				<li>Under <em>Header &amp; Footer Pages</em>, assign a header and footer page for each enabled language.</li>
				<li>Go to <strong>Settings → Permalinks</strong> and click <strong>Save Changes</strong> to flush rewrite rules after any setup change.</li>
			</ol>
			<p><strong>Tip:</strong> The quickest way to set up a new site is <strong>Appearance → Site Cleaner → Site Initialiser</strong>, which creates all required pages and configures the plugin in one click.</p>

			<hr>

			<h2>Required Pages</h2>
			<p>Every bilingual site needs these pages built in WPBakery:</p>
			<table class="widefat striped" style="max-width:600px;">
				<thead><tr><th>Page title</th><th>Slug</th><th>Language</th></tr></thead>
				<tbody>
					<tr><td>Header [IT]</td><td><code>header</code></td><td>IT</td></tr>
					<tr><td>Header [EN]</td><td><code>header-en</code></td><td>EN</td></tr>
					<tr><td>Footer [IT]</td><td><code>footer</code></td><td>IT</td></tr>
					<tr><td>Footer [EN]</td><td><code>footer-en</code></td><td>EN</td></tr>
					<tr><td>Homepage IT</td><td><code>homepage</code></td><td>IT</td></tr>
					<tr><td>Homepage EN</td><td><code>homepage-en</code></td><td>EN</td></tr>
					<tr><td>Sub-page IT (e.g. Chi siamo)</td><td><code>chi-siamo</code></td><td>IT</td></tr>
					<tr><td>Sub-page EN (e.g. About)</td><td><code>about</code></td><td>EN</td></tr>
				</tbody>
			</table>
			<p>The theme renders the correct header/footer for the active language automatically.</p>

			<hr>

			<h2>Linking Translations</h2>
			<p>Open any page in the editor. In the <strong>Language Settings</strong> meta box (right-hand sidebar):</p>
			<ol>
				<li>Set <strong>Page Language</strong> to the language this page is written in.</li>
				<li>Under <strong>Translation Links</strong>, pick the equivalent page in the other language from the dropdown.</li>
			</ol>
			<p>The flag switcher in the menu will then redirect visitors to the correct translated page when they switch language. You can also bulk-manage links at <strong>Mira Language → Translation Links</strong>.</p>

			<hr>

			<h2>URL Structure</h2>
			<p>Pages are served under language-prefix paths:</p>
			<ul>
				<li><code>/sitename/it/page-slug/</code> — Italian</li>
				<li><code>/sitename/en/page-slug/</code> — English</li>
			</ul>
			<p>If URLs return 404, go to <strong>Settings → Permalinks → Save Changes</strong> to flush rewrite rules.</p>

			<hr>

			<h2>Flags in the Menu</h2>
			<p>Two flag icons (e.g. 🇮🇹 🇬🇧) are injected at the end of the <em>main-nav</em> menu. Clicking a flag sets the <code>mira_language</code> cookie and redirects to the translated version of the current page if one is linked, or to the language homepage otherwise.</p>
			<p><strong>Flag style</strong> is set in Settings → Flag Display: <em>Emoji</em> (🇬🇧) or <em>Text</em> (EN).</p>

			<hr>

			<h2>Settings Reference</h2>
			<table class="widefat striped" style="max-width:700px;">
				<thead><tr><th>Setting</th><th>What it does</th></tr></thead>
				<tbody>
					<tr><td><strong>Default Language</strong></td><td>Language served at the root URL and used as fallback.</td></tr>
					<tr><td><strong>Enabled Languages</strong></td><td>Activates language prefixes and adds header/footer page slots.</td></tr>
					<tr><td><strong>Add to Menu</strong></td><td>Injects flag switcher into the nav menu automatically.</td></tr>
					<tr><td><strong>Flag Display</strong></td><td>Emoji flags or text codes (EN / IT).</td></tr>
					<tr><td><strong>Automatic Redirects</strong></td><td>Redirects visitors based on their saved cookie when a translation exists.</td></tr>
					<tr><td><strong>Language Prefix in Titles</strong></td><td>Adds [IT] / [EN] prefix to admin page titles for clarity.</td></tr>
					<tr><td><strong>Header / Footer Pages</strong></td><td>Assigns a WPBakery page to use as header or footer for each language.</td></tr>
				</tbody>
			</table>

		</div>
	</div>
	<?php
}
