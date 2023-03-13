import { Plugin } from 'ckeditor5/src/core';
import CKEditorTemplatesCommand from './ckeditorTemplatesCommand';

/**
 * Handles the plugin functionality.
 */
export default class CKEditorTemplatesEditing extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [
      CKEditorTemplatesCommand
    ];
  }

  /**
   * @inheritdoc
   */
  init() {
    const editor = this.editor;
    const plugin = 'ckeditorTemplates';

    const schema = {
      allowWhere: '$block',
      isObject: true,
      isContent: true,
      isBlock: true
    };

    this.editor.model.schema.register(plugin, schema);
    this.editor.commands.add(plugin, new CKEditorTemplatesCommand(editor));
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'ckeditorTemplatesEditing';
  }
}
