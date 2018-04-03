<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('netatmoWelcome');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
  <div class="col-sm-2">
    <div class="bs-sidebar">
      <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
        <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
        <?php
foreach ($eqLogics as $eqLogic) {
	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
	echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
}
?>
     </ul>
   </div>
 </div>
 <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
   <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
   <div class="eqLogicThumbnailContainer">
    <div class="cursor eqLogicAction" data-action="gotoPluginConf" style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
      <center>
        <i class="fa fa-wrench" style="font-size : 5em;color:#767676;"></i>
      </center>
      <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Configuration}}</center></span>
    </div>
  </div>
  <legend><i class="fa fa-video-camera"></i>  {{Mes Welcomes}}
  </legend>
  <?php
if (count($eqLogics) == 0) {
	echo "<br/><br/><br/><center><span style='color:#767676;font-size:1.2em;font-weight: bold;'>{{Vous n'avez pas encore de caméra Welcome, aller sur Général -> Plugin et cliquez sur synchroniser pour commencer}}</span></center>";
} else {
	?>
   <div class="eqLogicThumbnailContainer">
    <?php
foreach ($eqLogics as $eqLogic) {
		$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
		echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
		echo "<center>";
		echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
		echo "</center>";
		echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
		echo '</div>';
	}
	?>
  </div>
  <?php }
?>
</div>
<div class="col-sm-10 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
  <legend><i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}<i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i></legend>

  <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
  <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
  <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
    <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
  </ul>

  <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
    <div role="tabpanel" class="tab-pane active" id="eqlogictab">

      <div class="row">
        <div class="col-sm-6">
          <form class="form-horizontal">
           <fieldset>
            <div class="form-group">
              <label class="col-sm-4 control-label">{{Nom de l'équipement Welcome Netatmo}}</label>
              <div class="col-sm-6">
                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement météo Netatmo}}"/>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-4 control-label" >{{Objet parent}}</label>
              <div class="col-sm-6">
                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                  <option value="">{{Aucun}}</option>
                  <?php
foreach (object::all() as $object) {
	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
               </select>
             </div>
           </div>
           <div class="form-group">
             <label class="col-sm-4 control-label"></label>
             <div class="col-sm-8">
              <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
              <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
            </div>
          </div>
        </div>
      </fieldset>
    </form>
  </div>
  <div class="col-sm-6">
    <center>
      <img src="' . $plugin->getPathImgIcon() . '" style="height : 300px;" />
    </center>
  </div>
</div>
<div role="tabpanel" class="tab-pane" id="commandtab">
  <table id="table_cmd" class="table table-bordered table-condensed">
    <thead>
      <tr>
        <th>{{Nom}}</th><th>{{Option}}</th><th>{{Action}}</th>
      </tr>
    </thead>
    <tbody>
    </tbody>
  </table>
</div>
</div>
</div>
</div>


<?php include_file('desktop', 'netatmoWelcome', 'js', 'netatmoWelcome');?>
<?php include_file('core', 'plugin.template', 'js');?>
