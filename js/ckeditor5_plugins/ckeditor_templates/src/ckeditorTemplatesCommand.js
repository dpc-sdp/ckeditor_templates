import { Command } from 'ckeditor5/src/core';

/**
 * Command for injecting code into the CKEditor.
 */
export default class CKEditorTemplatesCommand extends Command {

  /**
   * @inheritdoc
   */
  refresh() {
    const model = this.editor.model;
    const selection = model.document.selection;
    const allowedParent = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'ckeditorTemplates'
    );
    this.isEnabled = !!allowedParent;
  }

  /**
   * @inheritdoc
   */
  execute(htmlCode, replace) {
    const editor = this.editor;
    const data = editor.data;

    if (replace) {
      data.set(htmlCode);
    }
    else {
      const model = editor.model;
      model.change(() => {
        const viewFragment = data.processor.toView(htmlCode);
        const modelFragment = data.toModel(viewFragment);
        model.insertContent(modelFragment);
      });
    }
  }

}
