import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

class AttachmentPickerButton extends StatelessWidget {
  const AttachmentPickerButton({super.key, required this.conversationId});
  final String conversationId;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: const Icon(Icons.attach_file),
      tooltip: 'Attach file',
      onPressed: () => _pick(context),
    );
  }

  Future<void> _pick(BuildContext context) async {
    final result = await FilePicker.platform.pickFiles(allowMultiple: false);
    if (result == null || result.files.isEmpty) return;
    // TODO: upload via ClientApiService and send attachment message
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('File selected: ${result.files.first.name}. Upload coming in M2.1.')),
      );
    }
  }
}
