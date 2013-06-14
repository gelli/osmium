<?php
/* Osmium
 * Copyright (C) 2012, 2013 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium\Page\NewLoadout;

require __DIR__.'/../inc/root.php';
require __DIR__.'/../inc/ajax_common.php';

const RELATIVE = '..';

if(isset($_GET['import']) && $_GET['import'] === 'dna') {
    $dna = $_GET['dna'];

    /* XXX: maybe this needs to be cached, this can be quite expansive */
    $fit = \Osmium\Fit\try_parse_fit_from_shipdna($dna, 'New DNA-imported loadout', $errors);

    if($fit === false) {
        \Osmium\Fatal(404, "Nonsensical DNA string");
    } else {
        $tok = \Osmium\State\get_unique_new_loadout_token();
        \Osmium\State\put_new_loadout($tok, $fit);

        header('Location: ../'.$tok);
        die();
    }
}

if(!isset($_GET['token'])) {
	$tok = \Osmium\State\get_unique_new_loadout_token();

	\Osmium\Fit\create($fit);
	\Osmium\State\put_new_loadout($tok, $fit);

	header('Location: ./new/'.$tok);
	die();
} else {
	$tok = $_GET['token'];
	$fit = \Osmium\State\get_new_loadout($tok);

	if(!is_array($fit)) {
		/* Invalid token? */
		header('Location: ../new');
		die();
	}
}

\Osmium\Chrome\print_header('Create a new loadout', RELATIVE, false);

echo "<h1>Create a new loadout</h1>\n";

echo "<div id='nlattribs'>
<section id='ship'></section>
<section id='control'>
<form method='GET' action='./".$tok."'>
<input type='button' name='reset_loadout' id='reset_loadout' value='Reset loadout' />\n";

if(\Osmium\State\is_logged_in()) {
	echo "<input type='button' name='submit_loadout' id='submit_loadout' value='Save loadout' />\n";
} else {
	echo "<input type='button' name='export_loadout' id='export_loadout' value='Export loadout' /><br />\n";
	echo "Export format: <select name='export_type' id='export_type'>\n";
	foreach(\Osmium\Fit\get_export_formats() as $k => $f) {
		echo "<option value='".htmlspecialchars($k, ENT_QUOTES)."'>".htmlspecialchars($f[0])."</option>\n";
	}
	echo "</select>\n";
}

echo "</form>
</section>
<section id='attributes'>
<div class='compact' id='computed_attributes'>
<p class='placeholder loading'>
Loading attributes<span>…</span>
</p>
</div>
</section>
</div>\n";

echo "<div id='nlsources'>
<ul class='tabs'>
<li><a href='#search'>Search</a></li>
<li><a href='#browse'>Browse</a></li>
<li><a href='#shortlist'>Shortlist</a></li>
</ul>\n";

echo "<section id='search'>
<form method='get' action='?'>
<ul class='filters'></ul>
<div class='query'>
<div><input type='search' name='q' placeholder='Search modules, ships, implants…' /></div>
<input type='submit' value='Search' />
</div>
</form>
<ul class='results'></ul>
</section>\n";

echo "<section id='browse'>
<ul class='filters'></ul>
<p class='placeholder loading'>
Fetching the list of types<span>…</span>
</p>
</section>\n";

echo "<section id='shortlist'>
<ul class='filters'></ul>
<p class='placeholder loading'>
Fetching shortlist<span>…</span>
</p>
</section>\n";

echo "</div>\n";

echo "<div id='nlmain'>
<ul class='tabs'>
<li><a href='#modules'>Modules &amp; Charges</a></li>
<li><a href='#drones'>Drones</a></li>
<li><a href='#presets'>Presets</a></li>
<li><a href='#metadata'>Metadata</a></li>
</ul>\n";

echo "<section id='presets'>\n";
$presetactions = "<input type='button' class='createpreset' value='Create preset' />\n<input type='button' class='renamepreset' value='Rename preset' />\n<input type='button' class='clonepreset' value='Clone preset' />\n<input type='button' class='deletepreset' value='Delete preset' />";
\Osmium\Forms\print_form_begin();
\Osmium\Forms\print_generic_row('spreset', "<label for='spreset'>Preset</label>", "<select id='spreset' name='spreset'></select><br />\n".$presetactions."\n", 'rpresets');
\Osmium\Forms\print_textarea('Preset description', 'tpresetdesc');
\Osmium\Forms\print_separator();
\Osmium\Forms\print_generic_row('scpreset', "<label for='scpreset'>Charge preset</label>", "<select id='scpreset' name='scpreset'></select><br />\n".$presetactions."\n", 'rchargepresets');
\Osmium\Forms\print_textarea('Charge preset description', 'tcpresetdesc');
\Osmium\Forms\print_separator();
\Osmium\Forms\print_generic_row('sdpreset', "<label for='sdpreset'>Drone preset</label>", "<select id='sdpreset' name='sdpreset'></select><br />\n".$presetactions."\n", 'rdronepresets');
\Osmium\Forms\print_textarea('Drone preset description', 'tdpresetdesc');
\Osmium\Forms\print_form_end();
echo "</section>\n";

echo "<section id='metadata'>\n";
\Osmium\Forms\print_form_begin();
\Osmium\Forms\print_generic_field('Loadout title', 'text', 'name', 'name');
\Osmium\Forms\print_textarea('Description<br /><small>(optional)</small>', 'description', 'description');
\Osmium\Forms\print_generic_field('Tags<br /><small>(space-separated, '.\Osmium\Fit\MAXIMUM_TAGS.' maximum)</small>', 'text', 'tags', 'tags');
if(\Osmium\State\is_logged_in()) {
	\Osmium\Forms\print_separator();

	\Osmium\Forms\print_select(
		'Can be seen by', 'view_perms', 
		array(
			\Osmium\Fit\VIEW_EVERYONE => 'everyone',
			\Osmium\Fit\VIEW_PASSWORD_PROTECTED => 'everyone but require a password',
			\Osmium\Fit\VIEW_ALLIANCE_ONLY => 'my alliance only',
			\Osmium\Fit\VIEW_CORPORATION_ONLY => 'my corporation only',
			\Osmium\Fit\VIEW_OWNER_ONLY => 'only me',
			), null, 'view_perms');

	\Osmium\Forms\print_select(
		'Can be edited by', 'edit_perms', 
		array(
			\Osmium\Fit\EDIT_OWNER_ONLY => 'only me',
			\Osmium\Fit\EDIT_OWNER_AND_FITTING_MANAGER_ONLY => 'me and anyone in my corporation with the Fitting Manager role',
			\Osmium\Fit\EDIT_CORPORATION_ONLY => 'anyone in my corporation',
			\Osmium\Fit\EDIT_ALLIANCE_ONLY => 'anyone in my alliance',
			), null, 'edit_perms');

	\Osmium\Forms\print_select(
		'Visibility', 'visibility', 
		array(
			\Osmium\Fit\VISIBILITY_PUBLIC => 'public (will appear on the homepage and in search results)',
			\Osmium\Fit\VISIBILITY_PRIVATE => 'private (will not appear in search results)',
			), null, 'visibility');

	\Osmium\Forms\print_generic_field('Password', 'password', 'pw', 'pw');
}
\Osmium\Forms\print_form_end();
echo "</section>\n";

echo "<section id='modules'>\n";
$stypes = \Osmium\Fit\get_slottypes_names();
foreach($stypes as $type => $fname) {
    if($type === "high" || $type === "medium") {
        $groupstatus = "grouped";
        $groupedcharges = "<small class='groupcharges'>Charges are grouped</small>";
    } else {
        $groupstatus = "ungrouped";
        $groupedcharges = "";
    }

	echo "<div class='slots $type $groupstatus'>\n<h3>".htmlspecialchars($fname)
		." <span>$groupedcharges<small class='counts'></small></span></h3>\n";
	echo "<ul></ul>\n";
	echo "</div>\n";
}
echo "</section>\n";

echo "</div>\n";

\Osmium\Chrome\print_js_code(
"osmium_cdatastaticver = ".\Osmium\CLIENT_DATA_STATICVER.";
osmium_staticver = ".\Osmium\STATICVER.";
osmium_relative = '".RELATIVE."';
osmium_token = '".\Osmium\State\get_token()."';
osmium_clftoken = '".$tok."';
osmium_shortlist = ".json_encode(\Osmium\AjaxCommon\get_module_shortlist()).";
osmium_clf = ".json_encode(\Osmium\Fit\export_to_common_loadout_format_1($fit, true, true, true)).";"
);

\Osmium\Chrome\print_js_snippet('tabs');
\Osmium\Chrome\print_js_snippet('modal');
\Osmium\Chrome\print_js_snippet('context_menu');
\Osmium\Chrome\print_js_snippet('loadout_common');
\Osmium\Chrome\print_js_snippet('new_loadout');
\Osmium\Chrome\print_js_snippet('new_loadout-control');
\Osmium\Chrome\print_js_snippet('new_loadout-sources');
\Osmium\Chrome\print_js_snippet('new_loadout-ship');
\Osmium\Chrome\print_js_snippet('new_loadout-presets');
\Osmium\Chrome\print_js_snippet('new_loadout-metadata');
\Osmium\Chrome\print_js_snippet('new_loadout-modules');
\Osmium\Chrome\print_js_snippet('formatted_attributes');
\Osmium\Chrome\print_footer();
