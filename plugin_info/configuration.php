<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
	include_file('desktop', '404', 'php');
	die();
}
?>

<form class="form-horizontal">
	<fieldset>
        <div class="form-group">
            </div>
		</div>
		<div class="form-group">
			<div class="col-lg-4">
				<a class="btn btn-info" style="margin-bottom : 5px;" title="Creer Token" href="https://account.smartthings.com/tokens" target="_blank">
				<i class="fa fa-add"></i>
				{{Creation d'un token d'acces personel sur le site Smarthings (Suivre la documentation)}}
				</a>
			</div>
		</div>
		<div class="form-group">
			<label class="col-sm-3 control-label">
				{{Token SmartThings}}
				<sup>
					<i class="fa fa-question-circle tooltips" title="{{Creation d'un token d'acces personel sur le site Smarthings (Suivre la documentation) }}" " style="font-size : 1em;color:grey;"></i>
				</sup>
			</label>
			<div class="col-sm-3">
				<input type="text" size="40" class="configKey form-control" data-l1key="token"/>
			</div>
		</div>
  </fieldset>
</form>

<script>
$('.configKey[data-l1key=demo_mode]').on('change', function() {
	if ($(this).value()=='1') { $('#bt_loginDemoSmartThings').show(); $('#bt_loginSmartThings').hide();} else { $('#bt_loginDemoSmartThings').hide(); $('#bt_loginSmartThings').show();}
});
$('#bt_loginSmartThings').on('click', function () {
	$.ajax({ // fonction permettant de faire de l'ajax
		type: "POST", // methode de transmission des données au fichier php
		url: "plugins/smartthings/core/ajax/smartthings.ajax.php", // url du fichier php
		data: {
			action: "loginSmartThings"
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				$('#div_alert').showAlert({message: data.result, level: 'danger'});
				return;
			}
			window.location.href = data.result.redirect;
		}
	});
});
$('#bt_loginDemoSmartThings').on('click', function () {
	$.ajax({ // fonction permettant de faire de l'ajax
		type: "POST", // methode de transmission des données au fichier php
		url: "plugins/smartthings/core/ajax/smartthings.ajax.php", // url du fichier php
		data: {
			action: "loginSmartThings"
		},
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				$('#div_alert').showAlert({message: data.result, level: 'danger'});
				return;
			}
		}
	});
});
</script>
