<div class="portlet box green">
	<div class="portlet-title">
		<div class="caption">Редактирование контента</div>
	</div>
	<div class="portlet-body form">
		<form action="{{ url('content/edit/'~info['id']~'/') }}" method="post" class="form-horizontal form-bordered">
			<div class="form-body">
				<div class="form-group">
					<label class="col-md-3 control-label">Алиас</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="alias" value="{{ info['alias'] }}" title="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-3 control-label">Название</label>
					<div class="col-md-9">
						<input type="text" class="form-control" name="title" value="{{ info['title'] }}" title="">
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-3 control-label">Текст</label>
					<div class="col-md-9">
						<textarea name="html" cols="" rows="10" class="form-control" title="">{{ info['html'] }}</textarea>
					</div>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn green">Сохранить</button>
				</div>
			</div>
		</form>
	</div>
</div>