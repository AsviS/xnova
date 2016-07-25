<div class="portlet box green">
	<div class="portlet-title">
		<div class="caption"><?=_getText('adm_search_pl') ?></div>
	</div>
	<div class="portlet-body form">
		<form action="<?=$this->url->get('admin/manager/data/') ?>" method="post" class="form-horizontal form-bordered">
			<input type="hidden" name="send" value="y">
			<div class="form-body">
				<div class="form-group">
					<label class="col-md-3 control-label"><?=_getText('adm_player_nm') ?></label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="username" title="">
					</div>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn green"><?=_getText('adm_bt_search') ?></button>
				</div>
			</div>
		</form>
	</div>
</div>