<?php
// Bearbeiten Dokument Modals
if ($selectedStaff && isset($selectedStaff['documents'])):
    foreach ($selectedStaff['documents'] as $document):
?>
    <div class="modal fade" id="editDocumentModal<?php echo $document['id']; ?>" tabindex="-1" aria-labelledby="editDocumentModalLabel<?php echo $document['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDocumentModalLabel<?php echo $document['id']; ?>">Dokument bearbeiten</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="staff.php">
                    <input type="hidden" name="action" value="edit_document">
                    <input type="hidden" name="staff_id" value="<?php echo $selectedStaff['id']; ?>">
                    <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                    
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="document_title_<?php echo $document['id']; ?>">Dokumenttitel *</label>
                            <input type="text" class="form-control" id="document_title_<?php echo $document['id']; ?>" name="document_title" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="document_description_<?php echo $document['id']; ?>">Beschreibung</label>
                            <textarea class="form-control" id="document_description_<?php echo $document['id']; ?>" name="document_description" rows="2"><?php echo htmlspecialchars($document['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="document-type-info alert alert-info">
                            <strong>Dokumenttyp:</strong> 
                            <?php 
                            $documentType = $document['document_type'] ?? 'text';
                            if ($documentType === 'text') {
                                echo 'Textdokument';
                            } elseif ($documentType === 'url') {
                                echo 'URL / Externer Link';
                            } elseif ($documentType === 'file') {
                                echo 'Datei (' . strtoupper($document['file_type'] ?? '') . ')';
                            }
                            ?>
                            <br>
                            <small class="text-muted">Um den Dokumenttyp zu ändern, löschen Sie dieses Dokument und erstellen Sie ein neues.</small>
                        </div>
                        
                        <?php if ($documentType === 'text'): ?>
                            <div class="form-group">
                                <label for="document_content_<?php echo $document['id']; ?>">Dokumentinhalt *</label>
                                <textarea class="form-control" id="document_content_<?php echo $document['id']; ?>" name="document_content" rows="10"><?php echo htmlspecialchars($document['content'] ?? ''); ?></textarea>
                            </div>
                        <?php elseif ($documentType === 'url'): ?>
                            <div class="form-group">
                                <label for="document_url_<?php echo $document['id']; ?>">URL *</label>
                                <input type="url" class="form-control" id="document_url_<?php echo $document['id']; ?>" name="document_url" value="<?php echo htmlspecialchars($document['url'] ?? ''); ?>" placeholder="https://beispiel.de/dokument">
                            </div>
                        <?php elseif ($documentType === 'file'): ?>
                            <div class="mt-4">
                                <div class="alert alert-warning">
                                    <strong>Hinweis:</strong> Um die Datei zu ersetzen, löschen Sie dieses Dokument und laden Sie ein neues hoch.
                                </div>
                                
                                <p>
                                    <strong>Aktuelle Datei:</strong> 
                                    <?php if (isset($document['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank">
                                            <?php echo htmlspecialchars(basename($document['file_path'])); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Keine Datei gefunden.</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
    endforeach;
endif;
?>