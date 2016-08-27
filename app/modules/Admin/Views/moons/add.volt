<div class="portlet box green">
	<div class="portlet-title">
		<div class="caption">Создать луну</div>
	</div>
	<div class="portlet-body form">
		<form action="{{ url('moons/add/') }}" method="post" class="form-horizontal form-bordered">
			<div class="form-body">
				<div class="form-group">
					<label class="col-md-3 control-label">Галактика</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="galaxy" title="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-3 control-label">Система</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="system" title="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-3 control-label">Планета</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="planet" title="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-3 control-label">user ID</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="user" title="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-3 control-label">ШВЛ</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="diameter" value="20" title="">
					</div>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn green">Создать</button>
				</div>
			</div>
		</form>
	</div>
</div>