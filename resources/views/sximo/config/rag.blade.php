@extends('layouts.app')

@section('content')
<div class="page-header">
	<h2> {{ $pageTitle }}  <small> {{ $pageNote }} </small> </h2>
</div>

<div class="card">
	<div class="card-body">
		@include('sximo.config.tab')
		
		<div class="pt-5">
			<div class="row">
				<!-- Upload Form -->
				<div class="col-md-5">
					<div class="card mb-4" style="border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
						<div class="card-header bg-primary text-white" style="background-color: #8b5cf6 !important; border-bottom: none;">
							<h5 class="card-title mb-0" style="font-size: 15px; font-weight: bold; color: #ffffff !important; display: flex; align-items: center;">
								<i class="fas fa-cloud-upload-alt me-2" style="color: #ffffff !important;"></i> {{ __('core.btn_upload') }} {{ __('core.tab_rag') }}
							</h5>
						</div>
						<div class="card-body">
							<form action="{{ url('sximo/config/rag/upload') }}" method="POST" enctype="multipart/form-data">
								@csrf
								
								<div class="form-group mb-3">
									<label for="reference_file" class="form-label" style="font-weight: 500;">Select Document File</label>
									<input type="file" name="reference_file" id="reference_file" class="form-control" required="true" accept=".txt,.md,.json,.html,.pdf,.docx,.xlsx,.pptx,.csv,.xml" />
									<div class="form-text text-muted mt-2" style="font-size: 12px; line-height: 1.5;">
										<i class="fas fa-info-circle text-info me-1"></i> Supported formats: <strong>.pdf, .docx, .xlsx, .pptx, .txt, .md, .csv, .json, .html</strong> (Max size: 20 MB).
										<br/>The system converts documents to structured <strong>Markdown</strong> and vectorizes them for RAG.
									</div>
								</div>
								
								<button type="submit" class="btn btn-primary btn-sm w-100" style="color: #ffffff !important; background-color: #8b5cf6 !important; border-color: #8b5cf6 !important; font-weight: 600;">
									<i class="fas fa-cogs me-1" style="color: #ffffff !important;"></i> {{ __('core.btn_upload') }} & Proses Dokumen
								</button>
							</form>
						</div>
					</div>

					<div class="alert alert-info" style="border-radius: 6px; font-size: 13px; line-height: 1.6;">
						<h5 style="font-size: 14px; font-weight: bold; margin-bottom: 8px;"><i class="fas fa-question-circle"></i> How does it work?</h5>
						<ol class="ps-3 mb-0">
							<li>The system converts your document into clean <strong>Markdown</strong> (preserving tables & headers via Microsoft MarkItDown).</li>
							<li>The Markdown text is split into readable <strong>chunks</strong> (overlapping text blocks).</li>
							<li>Each chunk is sent to the configured AI API (e.g. Gemini, OpenAI) to generate a mathematical <strong>vector embedding</strong>.</li>
							<li>When chatting in AI Chatbot, the AI retrieves relevant chunks to formulate accurate replies based on your files.</li>
						</ol>
					</div>
				</div>

				<!-- Document List Table -->
				<div class="col-md-7">
					<div class="card" style="border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
						<div class="card-header bg-light" style="border-bottom: 1px solid rgba(0,0,0,0.08);">
							<h5 class="card-title mb-0" style="font-size: 15px; font-weight: bold; color: #495057 !important; display: flex; align-items: center;">
								<i class="far fa-file-alt me-2" style="color: #495057 !important;"></i> Uploaded Documents Base
							</h5>
						</div>
						<div class="card-body p-0">
							<div class="table-responsive">
								<table class="table table-striped table-hover mb-0" style="font-size: 13px;">
									<thead>
										<tr class="bg-light">
											<th style="width: 50px;" class="text-center">#</th>
											<th>File Name</th>
											<th style="width: 130px;" class="text-center">Chunks Count</th>
											<th style="width: 160px;">Uploaded At</th>
											<th style="width: 120px;" class="text-center">Action</th>
										</tr>
									</thead>
									<tbody>
										@if(count($documents) > 0)
											@foreach($documents as $index => $doc)
												<tr>
													<td class="text-center">{{ $index + 1 }}</td>
													<td>
														<strong style="color: #2b303a;">{{ $doc->file_name }}</strong>
														<br/>
														<small class="text-muted" style="font-size: 11px;">Path: storage/app/{{ $doc->file_path }}</small>
													</td>
													<td class="text-center">
														<span class="badge bg-success text-white" style="font-size: 12px; padding: 4px 8px; border-radius: 4px;">
															{{ $doc->chunks_count }} chunks
														</span>
													</td>
													<td>
														{{ date('d M Y H:i', strtotime($doc->created_at)) }}
													</td>
													<td class="text-center">
														<a href="{{ url('sximo/config/rag/download-md/'.$doc->id) }}" 
														   class="btn btn-info btn-xs text-white" 
														   title="Download Converted Markdown (.md)"
														   style="padding: 2px 7px; margin-right: 3px;">
															<i class="fas fa-download"></i> .md
														</a>
														<a href="{{ url('sximo/config/rag/delete/'.$doc->id) }}" 
														   class="btn btn-danger btn-xs" 
														   onclick="return confirm('Are you sure you want to delete this document and all its corresponding vector chunks? This cannot be undone.');"
														   title="Delete Document"
														   style="padding: 2px 6px;">
															<i class="fas fa-trash-alt"></i>
														</a>
													</td>
												</tr>
											@endforeach
										@else
											<tr>
												<td colspan="5" class="text-center py-4 text-muted">
													<i class="far fa-folder-open fa-2x mb-2 d-block"></i>
													No reference documents uploaded yet. Upload a document to improve AI knowledge.
												</td>
											</tr>
										@endif
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@stop
