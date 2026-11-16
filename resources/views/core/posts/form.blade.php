@extends('layouts.app')

@section('content')
<div class="page-header"><h2>  {{ $pageTitle }} <small> {{ $pageNote }} </small> </h2></div>



		<ul class="parsley-error-list">
			@foreach($errors->all() as $error)
				<li>{{ $error }}</li>
			@endforeach
		</ul>			
			
		<form action="{{ url('cms/posts?return='.$return) }}" class="form-vertical form-material validated" method="POST" enctype="multipart/form-data">
		
		 <div class="row">
			<div class="col-md-9">

						<ul class="nav nav-tabs form-tab" >
						  <li class="nav-item"><a href="#info" data-bs-toggle="tab" class="nav-link active"> Page Content </a></li>
						  <li class="nav-item"><a href="#meta" data-bs-toggle="tab" class="nav-link" > Meta & Description </a></li>
						  <li class="nav-item"><a href="#image" data-bs-toggle="tab" class="nav-link"> Images </a></li>
						</ul>	

					<div class="tab-content pt-5 bg-white">
						  <div class="tab-pane active m-t" id="info">

						  	<input type="hidden" name="pageID" value="{{ $row['pageID'] }}">
							<input type="hidden" name="pagetype" value="post">
							<input type="hidden" name="pageID" value="{{ $row['pageID'] }}">	
							<div class="form-group  " >
							<label > Categories    </label>									
							  <select class="form-control form-control-sm" name="cid">
							  	<option value=""> -- Select Category -- </option>
							  	@foreach($categories as $cat)
							  		<option value="{{ $cat->cid }}" @if($cat->cid == $row['cid']) selected @endif >{{ $cat->name }}</option>
							  	@endforeach

							  </select>			
						  </div> 

									  <div class="form-group  " >
										<label > Post Title    </label>									
										<input type="text" name="title" value="{{ $row['title'] }}" class="form-control" placeholder="">
									  </div> 					
									  <div class="form-group  " >
										<label for="ipt" class=" btn-success  btn btn-sm">  {!! url('post/read/')!!}  </label>							
											 
										<input type="text" name="alias" value="{{ $row['alias'] }}" class="form-control input-sm" placeholder="" style="width:150px; display:inline-block;">
											
									  </div> 					
									  <div class="form-group  " >
										<label > Post Content    </label>							
										  <textarea name='note' rows='25' id='note' class='form-control editor'  
				           >{{ $row['note'] }}</textarea> 						
									  </div> 					
									   					
							</div>
							<div class="tab-pane m-t" id="meta">		  					
									  <div class="form-group  " >
										<label > Metakey    </label>
										 <textarea name='metakey' rows='5' id='metakey' class='form-control '  
				           >{{ $row['metakey'] }}</textarea> 						
									  </div> 					
									  <div class="form-group  " >
										<label > Metadesc    </label>									
										  <textarea name='metadesc' rows='5' id='metadesc' class='form-control '  
				           >{{ $row['metadesc'] }}</textarea> 						
									  </div> 	
							</div>

							<div class="tab-pane m-t" id="image">
								<div class="form-group  " >
									<label > Images    </label>
									<div class="fileUpload btn " > 
									    <span>  <i class="fa fa-camera"></i>  </span>
									    <div class="title"> Browse File </div>
									    <input type="file" name="image" class="upload"   accept="image/x-png,image/gif,image/jpeg"     />
									</div>

									
									<div class="image-preview preview-upload">
									{!! SiteHelpers::showUploadedFile($row['image'],'/uploads/images/posts/' , 400) !!}	
									</div>				
								  </div>


							</div>

					</div>	
			</div>
			
			<div class="col-md-3">
						
							  <div class="form-group  " >
								<label> Post Status :  </label>
								<div class="demo-radio-button">					
								  <input  type='radio' name='status'  value="enable" required class="minimal-green" 
								  @if( $row['status'] =='enable')  	checked	  @endif	id="p-s-e" 			  
								   /> 
								  <label for="p-s-e">Enable</label>
												
								  <input  type='radio' name='status'  value="disable" required class="minimal-green" 
								   @if( $row['status'] =='disable')  	checked	  @endif id="p-s-d" 			  
								   /> 
								  <label for="p-s-d">Disabled</label>
								</div> 					 
							  </div>									
									   					
									  <div class="form-group  " >
										<label for="ipt" class=" control-label "> Created    </label>								  
										<div class="input-group m-b">
										<input type="text" name="created" value="{{ $row['created'] }}" class="form-control input-sm date" style="width:150px !important;">
										</div>				 						
									  </div> 					

									  <div class="form-group  demo-checkbox" >
									  <label for="ipt"> Who can view this page ? </label>
										@foreach($groups as $group) 
										<div class="checkbox checkbox-success">					
										  <input  type='checkbox' name='group_id[{{ $group['id'] }}]'    value="{{ $group['id'] }}"
										  @if($group['access'] ==1 or $group['id'] ==1)
										  	checked
										  @endif	id="v-{{ $group['id'] }}"			 
										   /> 
										  <label for="v-{{ $group['id'] }}">{{ $group['name'] }}</label>
										</div>  
										@endforeach	
											  
									  </div>
 		
									   <div class="form-group  " >
										<label> Show for Guest ? unlogged  </label>
										<div class=""><input  type='checkbox' name='allow_guest'  class="minimal-green" 
					 						@if($row['allow_guest'] ==1 ) checked  @endif	 id="guest" 
										   value="1"	/> <label for="guest">Allow Guest ?  </lable>
										   </div>
									  </div>		

									  <div class="form-group  " >
										<label>  Set as Headline </label>
										<div class=""><input  type='checkbox' name='headline'  class="minimal-green"  id="is_headline"
					 						@if($row['headline'] ==1 ) checked  @endif	id="headline" 
										   value="1"	/> <label for="headline"> headline   </lable>
										   </div>
									  </div>	



				<div class="form-group  " >
					<label > Labels    </label>									
					  <textarea name='labels' rows='2' id='labels' class='form-control '>{{ $row['labels'] }}</textarea> 						
				</div>


					
				  <div class="form-group">
					
					<button type="submit" name="apply" class="btn btn-info btn-sm btn-flat" ><i class="icon-checkmark-circle2"></i> Apply</button>
					<button type="submit" name="submit" class="btn btn-primary btn-sm btn-flat" ><i class="icon-bubble-check"></i> {{ Lang::get('core.sb_save') }}</button>
					<button type="button" onclick="location.href='{{ URL::to('cms/posts?return='.$return) }}' " class="btn btn-warning btn-sm btn-flat"><i class="icon-cancel-circle2 "></i>  {{ Lang::get('core.sb_cancel') }} </button>
						
				</div>	

			
			</div>
			<div style="clear:both;"></div> 
			<input type="hidden" name="action_task" value="save" />
		</div>	
		 </form>	

		 
<style type="text/css">
	.preview-upload {

	}
	.img-circle {
		border-radius: 6px !important;
	}
</style>		 
   <script type="text/javascript">
	$(document).ready(function() { 
		
		 

		$('.removeMultiFiles').on('click',function(){
			var removeUrl = '{{ url("posts/removefiles?file=")}}'+$(this).attr('url');
			$(this).parent().remove();
			$.get(removeUrl,function(response){});
			$(this).parent('div').empty();	
			return false;
		});		
		
	});
	</script>	
			 
@stop