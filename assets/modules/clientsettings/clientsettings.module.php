<?php
	
if (IN_MANAGER_MODE != 'true' || empty($modx) || !($modx instanceof DocumentParser)) {
	die('Please use the MODX Content Manager instead of accessing this file directly.');
}
global $_lang;	
$managerPath = $modx->getManagerPath();
	
if (!$modx->hasPermission('exec_module')) {
	$modx->sendRedirect('index.php?a=106');
}
	
if (!is_array($modx->event->params)) {
	$modx->event->params = [];
}
	
if (!function_exists('renderFormElement')) {
	include_once(MODX_MANAGER_PATH . 'includes/tmplvars.commands.inc.php');
	include_once(MODX_MANAGER_PATH . 'includes/tmplvars.inc.php');
}
	
if (isset($_REQUEST['stay'])) {
	$_SESSION['stay'] = $_REQUEST['stay'];
	} else if (isset($_SESSION['stay'])) {
	$_REQUEST['stay'] = $_SESSION['stay'];
}
	
$stay = isset($_REQUEST['stay']) ? $_REQUEST['stay'] : '';
	
$menu = isset($_GET['menu']) && is_string($_GET['menu']) ? $_GET['menu'] : 'default';
	
$tabs = [];
	
foreach (glob(__DIR__ . '/config/*.php') as $file) {
	$tab = include $file;
	
	if (!empty($tab) && is_array($tab)) {
		$tabMenu = isset($tab['menu']['alias']) ? $tab['menu']['alias'] : 'default';
		
		if ($tabMenu == $menu) {
			$tabs[pathinfo($file, PATHINFO_FILENAME)] = $tab;
		}
	}
}
	
	
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$fields = [];
		
	foreach ($tabs as $tab) {
		foreach (array_keys($tab['settings']) as $field) {
			$postfield = 'tv' . $field;
			
			$type = $tab['settings'][$field]['type'];
			
			if (isset($_POST[$postfield])) {
				$value = $_POST[$postfield];
				} else if (isset($tab['settings'][$field]['default_value'])) {
				$value = $tab['settings'][$field]['default_value'];
				} else {
				$value = '';
			}
			
			switch ($type) {
				case 'url':
                   if ($_POST[$postfield . '_prefix'] != '--') {
                       $value = str_replace(array (
					"feed://",
					"ftp://",
					"http://",
					"https://",
					"mailto:"
                       ), "", $value);
                       $value = $_POST[$postfield . '_prefix'] . $value;
				}
                   break;
				
				case 'custom_tv:multitv': {
					$json = @json_decode($value);
					
					if (isset($json->fieldValue)) {
						$value = json_encode($json->fieldValue, JSON_UNESCAPED_UNICODE);
					}
					break;
				}
				
				default:
                   if (is_array($value)) {
                       $value = implode("||", $value);
				}
                   break;
			}
			
			$fields[$field] = [$params['prefix'] . $field, $value];
		}
	}
	
	$modx->invokeEvent('OnBeforeClientSettingsSave', [
       'fields' => &$fields,
	]);
	
	
	if (count($_POST['settings']))
	{			
		$files = new RecursiveDirectoryIterator(MODX_BASE_PATH."assets/modules/clientsettings/config/");
		foreach($files as $file) unlink($file->getRealPath());
		
		
		foreach($_POST['settings'] as $key => $val)
		{
			$conf = array();
			$conf['caption'] = $val['caption'] ? $val['caption'] : 'Untitled tab';
			$conf['introtext']  = $val['introtext'];
			unset($val['caption']);
			unset($val['introtext']);
			foreach($val as $set)
			{
				$f = $set['field'];
				if ($f)
				{
					unset($set['field']);
					$conf['settings'][$f] = $set;
				}
			}				
			
			$text = "<?php".PHP_EOL.'return '.var_export($conf,1).';';		
			
			
			$f=fopen(MODX_BASE_PATH."assets/modules/clientsettings/config/".$key.".php",'w');
			fwrite($f,$text);
			fclose($f);
		}			
		
	}			
	if (!empty($fields)) {
		$modx->db->query("REPLACE INTO " . $modx->getFullTableName('system_settings') . " (setting_name, setting_value) VALUES " . implode(', ', array_map(function($row) use ($modx) {
			return "('" . $modx->db->escape($row[0]) . "', '" . $modx->db->escape($row[1]) . "')";
			}, $fields)));
	}
		
	$modx->invokeEvent('OnDocFormSave', [
       'mode' => 'upd',
       'id'   => 0,
	]);
		
	$modx->invokeEvent('OnClientSettingsSave', [
       'fields' => $fields,
	]);
	
	$modx->clearCache('full');
	
	if ($stay == 2) {
		$modx->sendRedirect('index.php?a=112&id=' . $_GET['id'] . '&menu=' . $menu);
		} else {
		$modx->sendRedirect('index.php?a=7&r=10');
	}
}
	
global $content, $_style, $lastInstallTime;
$content['richtext'] = 1;
	
if (!isset($_COOKIE['MODX_themeMode'])) {
	$_COOKIE['MODX_themeMode'] = '';
}
	
$userlang    = $modx->getConfig('manager_language');
$_customlang = include MODX_BASE_PATH . 'assets/modules/clientsettings/lang.php';
$title       = isset($_customlang[$userlang]) ? $_customlang[$userlang] : reset($_customlang);
$_lang       = [];
	
include MODX_MANAGER_PATH . 'includes/lang/' . $userlang . '.inc.php';

$richtextinit  = [];
$defaulteditor = $modx->getconfig('which_editor');
	
$richtextparams = [
   'editor'   => $defaulteditor,
   'elements' => [],
   'options'  => [],
];

foreach ($tabs as $tab) {
	foreach ($tab['settings'] as $field => $options) {
		if ($options['type'] != 'richtext') {
			continue;
		}
		
		$editor    = $defaulteditor;
		$tvoptions = [];
		
		if (!empty($options['options'])) {
			$tvoptions = array_merge($tvoptions, $options['options']);
		}
		
		if (!empty($options['elements'])) {
			$tvoptions = array_merge($tvoptions, $modx->parseProperties($options['elements']));
		}
		
		if (!empty($tvoptions) && isset($tvoptions['editor'])) {
			$editor = $tvoptions['editor'];
		};
		
		$richtextparams['elements'][] = 'tv' . $field;
		$richtextparams['options']['tv' . $field] = $tvoptions;
	}
}
	
if (!empty($richtextparams)) {
	$richtextinit = $modx->invokeEvent('OnRichTextEditorInit', $richtextparams);
	
	if (is_array($richtextinit)) {
		$richtextinit = implode($richtextinit);
	}
}
	
$picker = [
   'yearOffset' => $modx->getConfig('datepicker_offset'),
   'format'     => $modx->getConfig('datetime_format') . ' hh:mm:00',
];
	
include_once MODX_MANAGER_PATH . 'includes/header.inc.php';
	
?>

<h1>
    <i class="fa fa-cog"></i><?= $title ?>
</h1>



<form name="settings" method="post" id="mutate">
	<div id="actions">
		<div class="btn-group">
			<div class="btn-group dropdown">
				<a id="Button1" class="btn btn-success" href="javascript:;" onclick="save_settings();">
					<i class="fa fa-floppy-o"></i><span><?= $_lang['save'] ?></span>
				</a>
				
				<span class="btn btn-success plus dropdown-toggle"></span>
				
				<select id="stay" name="stay">
					<option id="stay2" value="2" <?= $stay == '2' ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
					<option id="stay3" value="" <?= $stay == '' ? ' selected="selected"' : '' ?>><?= $_lang['close'] ?></option>
				</select>
			</div>
			
			<a id="Button5" class="btn btn-secondary" href="<?= $managerPath ?>index.php?a=2">
				<i class="fa fa-times-circle"></i><span><?= $_lang['cancel'] ?></span>
			</a>
		</div>
	</div>
	
	<div class="sectionBody" id="settingsPane">
		<div class="tab-pane" id="documentPane">
			<script type="text/javascript">
				var tpSettings = new WebFXTabPane(document.getElementById('documentPane'), <?= ($modx->getConfig('remember_last_tab') == 1 ? 'true' : 'false') ?> );
			</script>
			
			<?php foreach ($tabs as $name => $tab): ?>
			<div class="tab-page" id="tab_<?= $name ?>">
				<h2 class="tab"><?= $tab['caption'] ?></h2>
				
				<script type="text/javascript">
					tpSettings.addTabPage(document.getElementById('tab_<?= $name ?>'));
				</script>
				
				<table border="0" cellspacing="0" cellpadding="3" style="font-size: inherit; line-height: inherit;">
					<?php if (!empty($tab['introtext'])): ?>
					<tr>
						<td class="warning" nowrap="" colspan="2">
							<?= $tab['introtext'] ?>
							<div class="split" style="margin-bottom: 20px; margin-top: 10px;"></div>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php foreach ($tab['settings'] as $field => $options): ?>
					<?php if ($options['type'] == 'divider'): ?>
					<tr>
						<td colspan="2">
							<h4 style="margin-top: 20px;"><?= $options['caption'] ?></h4>
						</td>
					</tr>
					<?php else: ?>
					<tr>
						<td class="warning" nowrap="">
							<?php if ($options['type'] === 'title'): ?>
							<div style="font-size:120%;padding:20px 0 10px;font-weight:bold;">
								<?= $options['caption'] ?>
							</div>
							<?php else: ?>
							<?= $options['caption'] ?> <br>
							<small>[(<?= $params['prefix'] . $field ?>)]</small>
							<?php endif; ?>
						</td>
						
						<td data-type="<?= $options['type'] ?>">
							
							<?php if ($options['type'] !== 'title'): ?>
							
							<?php
								$value = isset($modx->config[$params['prefix'] . $field]) ? $modx->config[$params['prefix'] . $field] : false;
								
								$row = [
								'type'         => $options['type'],
								'name'         => $field,
								'caption'      => $options['caption'],
								'id'           => $field,
								'default_text' => isset($options['default_text']) && $value === false ? $options['default_text'] : '',
								'value'        => $value,
								'elements'     => isset($options['elements']) ? $options['elements'] : '',
								];
							?>
							
							<?= renderFormElement(
								$row['type'],
								$row['name'],
								'',
								$row['elements'],
								$row['value'] !== false ? $row['value'] : $row['default_text'],
								isset($options['style']) ? 'style="' . $options['style'] . '"' : '',
								$row
							); ?>
							<?php endif; ?>
							
							<?php if (isset($options['note'])): ?>
							<div class="comment">
								<?= $options['note'] ?>
							</div>
							<?php endif; ?>
						</td>
					</tr>
					<?php endif; ?>
					
					<?php if ($options['type'] !== 'title'): ?>
					<tr>
						<td colspan="2"><div class="split"></div></td>
					</tr>
					<?php endif; ?>
					<?php endforeach; ?>
				</table>
			</div>
			<?php endforeach; ?>
			
			<div class="tab-page" id="tab_settings">
				<h2 class="tab"><i class="fa fa-cog" aria-hidden="true" style="margin-right:0;"></i></h2>
				<script type="text/javascript">
                    tpSettings.addTabPage(document.getElementById('tab_settings'));
				</script>
				<div class="tab-settings">
					<?php
						if (!count($tabs))
						{
							$tabs['tab10'] = array ('caption' => 'Untitled tab','introtext' => 'All fields on this tab', 'settings' => array ('untitled_field' =>     array ('caption' => '', 'type' => '',  'note' => '', 'default_text' => '')));
						}
						
						foreach ($tabs as $key => $tab):
					?>
					
					
					<div class="tab-setting">
						<a href="#" class="add_tab" title="<?=$_lang['cm_add_new_category'];?>"><i class="fa fa-plus-circle"></i></a>
						<a href="#" class="remove_tab" title="<?=$lang['delete'];?>"><i class="fa  fa-minus-circle"></i></a>							
						<div class="tab-caption">
							<label><?=$_lang['resource_title'];?></label>
							<input class="form-control caption" name="settings[<?=$key;?>][caption]" value="<?=$tab['caption'];?>">
						</div>
						<div class="tab-introtext">
							<label><?=$_lang['resource_summary'];?></label>
							<input class="form-control introtext" name="settings[<?=$key;?>][introtext]" value="<?=$tab['introtext'];?>">
						</div>
						
						<div class="table-responsive">
							<table class="table data" cellpadding="1" cellspacing="1">
								<thead>
									<tr>					
										<td class="tableHeader"><?=$_lang['name'];?></td>
										<td class="tableHeader"><?=$_lang['resource_title'];?></td>
										<td class="tableHeader"><?=$_lang['type'];?></td>
										<td class="tableHeader"><?=$_lang['resource_description'];?></td>
										<td class="tableHeader"><?=$_lang['set_default'];?></td>
										<td class="tableHeader" width="1%"> </td>					
									</tr>
								</thead>
								<tbody class="sort-str">
									<?php
										$i=0;
									foreach ($tab['settings'] as $field => $value): ?>
									
									
									<tr>					
										<td class="tableHeader">
											<input class="form-control" name="settings[<?=$key;?>][<?=$i;?>][field]" value="<?=$field;?>">
										</td>
										<td class="tableHeader">
											<input class="form-control" name="settings[<?=$key;?>][<?=$i;?>][caption]" value="<?=$value['caption'];?>">
										</td>
										<td class="tableHeader">											
											<select name="type" size="1" class="form-control" name="settings[<?=$key;?>][<?=$i;?>][type]">
												<optgroup label="Standard Type">
													<option value="text" <?= ($value['type'] == '' || $value['type'] == 'text' ? "selected='selected'" : "") ?>>Text</option>
													<option value="rawtext" <?= ($value['type'] == 'rawtext' ? "selected='selected'" : "") ?>>Raw Text (deprecated)</option>
													<option value="textarea" <?= ($value['type'] == 'textarea' ? "selected='selected'" : "") ?>>Textarea</option>
													<option value="rawtextarea" <?= ($value['type'] == 'rawtextarea' ? "selected='selected'" : "") ?>>Raw Textarea (deprecated)</option>
													<option value="textareamini" <?= ($value['type'] == 'textareamini' ? "selected='selected'" : "") ?>>Textarea (Mini)</option>
													<option value="richtext" <?= ($value['type'] == 'richtext' || $value['type'] == 'htmlarea' ? "selected='selected'" : "") ?>>RichText</option>
													<option value="dropdown" <?= ($value['type'] == 'dropdown' ? "selected='selected'" : "") ?>>DropDown List Menu</option>
													<option value="listbox" <?= ($value['type'] == 'listbox' ? "selected='selected'" : "") ?>>Listbox (Single-Select)</option>
													<option value="listbox-multiple" <?= ($value['type'] == 'listbox-multiple' ? "selected='selected'" : "") ?>>Listbox (Multi-Select)</option>
													<option value="option" <?= ($value['type'] == 'option' ? "selected='selected'" : "") ?>>Radio Options</option>
													<option value="checkbox" <?= ($value['type'] == 'checkbox' ? "selected='selected'" : "") ?>>Check Box</option>
													<option value="image" <?= ($value['type'] == 'image' ? "selected='selected'" : "") ?>>Image</option>
													<option value="file" <?= ($value['type'] == 'file' ? "selected='selected'" : "") ?>>File</option>
													<option value="url" <?= ($value['type'] == 'url' ? "selected='selected'" : "") ?>>URL</option>
													<option value="email" <?= ($value['type'] == 'email' ? "selected='selected'" : "") ?>>Email</option>
													<option value="number" <?= ($value['type'] == 'number' ? "selected='selected'" : "") ?>>Number</option>
													<option value="date" <?= ($value['type'] == 'date' ? "selected='selected'" : "") ?>>Date</option>
												</optgroup>
												<optgroup label="Custom Type">
													<option value="custom_tv" <?= ($value['type'] == 'custom_tv' ? "selected='selected'" : "") ?>>Custom Input</option>
													<?php
														$custom_tvs = scandir(MODX_BASE_PATH . 'assets/tvs');
														foreach($custom_tvs as $ctv) {
															if(strpos($ctv, '.') !== 0 && $ctv != 'index.html') {
																$selected = ($value['type'] == 'custom_tv:' . $ctv ? "selected='selected'" : "");
																echo '<option value="custom_tv:' . $ctv . '"  ' . $selected . '>' . $ctv . '</option>';
															}
														}
													?>
												</optgroup>
											</select>
										</td>
										<td class="tableHeader">
											<input class="form-control" name="settings[<?=$key;?>][<?=$i;?>][note]" value="<?=$value['note'];?>">
										</td>
										<td class="tableHeader">
											<input class="form-control" name="settings[<?=$key;?>][<?=$i;?>][default_text]" value="<?=$value['default_text'];?>">
										</td>
										<td class="tableHeader" width="1%"> 
											<ul class="elements_buttonbar"> 
												<li><a title="Добавить" class="add_field"><i class="fa fa-plus fa-fw"></i></a></li>
												<li><a title="Удалить" class="remove_field"><i class="fa fa-minus fa-fw"></i></a></li>						
											</ul>
										</td>					
									</tr>
									<?php 
										$i++;
										endforeach; 
									?>
									
									
									
								</tbody>
							</table>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div> 
		</div>
	</div>
</form>

<?= $richtextinit ?>


<?php
	
	$mmPath = MODX_BASE_PATH . 'assets/plugins/managermanager/mm.inc.php';
	
	if (is_readable($mmPath)) {
		include_once $mmPath;
		
		if (isset($jsUrls['ddTools'])) {
		?>
		<script>
			$j = jQuery;
		</script>
		<script src="<?= $jsUrls['mm']['url'] ?>"></script>
		<script src="<?= $jsUrls['ddTools']['url'] ?>"></script>
		<script src="<?= MODX_SITE_URL . 'assets/plugins/managermanager/widgets/showimagetvs/jquery.ddMM.mm_widget_showimagetvs.js' ?>"></script>
		<script src="<?= MODX_SITE_URL . 'assets/modules/clientsettings/clientsettings.js' ?>"></script>
		
		<script>
			<?= initJQddManagerManager(); ?>
			
			$j('[data-type="image"] > [type="text"]').mm_widget_showimagetvs({
				thumbnailerUrl: '',
				width: 300,
				height: 100,
			});
		</script>
        <?php
		}
	}
	
?>

<?= $modx->manager->loadDatePicker($modx->getConfig('mgr_date_picker_path')) ?>

<script>
	jQuery('input.DatePicker').each(function() {
		new DatePicker(this, {
			yearOffset: <?= $picker['yearOffset'] ?>,
			format:     '<?= $picker['format'] ?>',
			dayNames:   <?= $_lang['dp_dayNames'] ?>,
			monthNames: <?= $_lang['dp_monthNames'] ?>,
			startDay:   <?= $_lang['dp_startDay'] ?>
		});
	});
	
</script>
<style>
	.tab-setting{border-radius:0.25rem; border: 1px solid #e3e3e3;     padding: 14px 20px 4px 20px; margin-bottom:20px; position:relative; }
	a.add_tab{position: absolute;right: 25px;top: -12px !important;font-size: 1rem;color: #449d44;display: block;width: 16px;height: 16px;padding: 0; width: 25px !important;
	text-align: center;     background: transparent !important;}
	a.remove_tab {position: absolute;top: -12px !important;font-size: 1rem;display: block;height: 16px;padding: 0; right: 10px;    color: #c9302c;}			
	.hidden{display:none;}
	.showall{cursor:pointer; color:#3481bc; text-decoration:underline;}
	label{margin-bottom:0; margin-top:0.5em;}
	.table.data>tbody>tr{    border-left: 10px solid lightgrey; }
	.table.data>tbody>tr td{    cursor:move;}
</style>

<?php include_once MODX_MANAGER_PATH . 'includes/footer.inc.php'; ?>
