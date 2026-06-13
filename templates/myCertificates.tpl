{**
 * plugins/generic/academicCertificate/templates/myCertificates.tpl
 *
 * Role-aware My Certificates / Belgelerim page (Phase 2)
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.generic.academicCertificate.myCertificates.title"}

<div class="page page_my_certificates">
	<div class="container">
		<h1>{translate key="plugins.generic.academicCertificate.myCertificates.title"}</h1>
		<p>{translate key="plugins.generic.academicCertificate.myCertificates.description"}</p>

		<form class="certificate-type-filter" method="get" action="{url page="certificate" op="myCertificates"}">
			<label for="typeFilter">{translate key="plugins.generic.academicCertificate.myCertificates.filterType"}</label>
			<select name="type" id="typeFilter" onchange="this.form.submit()">
				{foreach from=$certificateTypeOptions key=typeKey item=typeLabel}
					<option value="{$typeKey|escape}" {if $typeFilter == $typeKey}selected{/if}>{$typeLabel|escape}</option>
				{/foreach}
			</select>
		</form>

		{if $certificates|@count > 0}
			<table class="certificate-list-table">
				<thead>
					<tr>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.certificateType"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.journalName"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.dateIssued"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.certificateNumber"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.submissionTitle"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.certificateCode"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.status"}</th>
						<th>{translate key="plugins.generic.academicCertificate.myCertificates.actions"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$certificates item=cert}
						<tr class="certificate-row certificate-row-{$cert.certificateType|escape}">
							<td>{$cert.certificateTypeLabel|escape}</td>
							<td>{$cert.journalName|escape}</td>
							<td>{$cert.dateIssued|escape}</td>
							<td>
								{if $cert.certificateNumber}
									<code>{$cert.certificateNumber|escape}</code>
								{else}
									&mdash;
								{/if}
							</td>
							<td>
								{if $cert.submissionTitle}
									{$cert.submissionTitle|escape}
								{else}
									<span class="certificate-privacy-hidden">{translate key="plugins.generic.academicCertificate.myCertificates.titleHidden"}</span>
								{/if}
							</td>
							<td>
								{if $cert.certificateCode}
									<code>{$cert.certificateCode|escape}</code>
								{else}
									&mdash;
								{/if}
							</td>
							<td>
								<span class="certificate-status certificate-status-{$cert.status|escape}">{$cert.statusLabel|escape}</span>
							</td>
							<td class="certificate-actions">
								{if $cert.canDownload && $cert.downloadUrl}
									<a href="{$cert.downloadUrl|escape}" class="certificate-download-btn" target="_blank">
										{translate key="plugins.generic.academicCertificate.downloadCertificate"}
									</a>
								{elseif $cert.status == 'pending'}
									<span class="certificate-pending-note">{translate key="plugins.generic.academicCertificate.myCertificates.pendingIssuance"}</span>
								{/if}
								{if $cert.verifyUrl}
									<a href="{$cert.verifyUrl|escape}" class="certificate-verify-link" target="_blank" rel="noopener">
										{translate key="plugins.generic.academicCertificate.myCertificates.verifyLink"}
									</a>
								{/if}
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p class="no-certificates">{translate key="plugins.generic.academicCertificate.myCertificates.noCertificates"}</p>
		{/if}
	</div>
</div>

{include file="frontend/components/footer.tpl"}
