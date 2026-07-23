import 'dart:io';

import 'package:flutter/material.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/theme/app_spacing.dart';
import '../data/image_source_service.dart';
import '../domain/capture_content_type.dart';
import '../domain/capture_memory_style.dart';
import 'capture_controller.dart';
import 'capture_state.dart';

final class CapturePage extends StatefulWidget {
  const CapturePage({required this.controller, super.key});

  static const pageKey = ValueKey('capture-page');

  final CaptureController controller;

  @override
  State<CapturePage> createState() => _CapturePageState();
}

final class _CapturePageState extends State<CapturePage> {
  final _textController = TextEditingController();
  String? _validationMessage;

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    key: CapturePage.pageKey,
    child: ListenableBuilder(
      listenable: widget.controller,
      builder: (context, _) {
        final state = widget.controller.state;
        final ready = _readyFrom(state);
        final busy =
            state is CaptureSelectingImage ||
            state is CaptureCroppingImage ||
            state is CaptureRecognizingText ||
            state is CaptureSaving;
        final progress = switch (state) {
          CaptureSelectingImage() => '正在打开图片…',
          CaptureCroppingImage() => '正在准备裁剪…',
          CaptureRecognizingText() => '正在本地识别英文…',
          CaptureSaving() => '正在保存到本机…',
          _ => null,
        };
        final failure = state is CaptureFailure ? state.message : null;
        _syncText(ready.text);

        return SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 620),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    AppStrings.captureTitle,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  const Text('手动输入，或从照片中识别需要记忆的英文。'),
                  const SizedBox(height: AppSpacing.lg),
                  TextFormField(
                    key: const Key('capture_text'),
                    controller: _textController,
                    enabled: !busy,
                    minLines: 5,
                    maxLines: 10,
                    textCapitalization: TextCapitalization.sentences,
                    decoration: InputDecoration(
                      labelText: '英文内容',
                      alignLabelWithHint: true,
                      border: const OutlineInputBorder(),
                      errorText: _validationMessage,
                    ),
                    onChanged: (value) {
                      if (_validationMessage != null) {
                        setState(() => _validationMessage = null);
                      }
                      widget.controller.editText(value);
                    },
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Wrap(
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.sm,
                    children: [
                      OutlinedButton.icon(
                        key: const Key('capture_camera'),
                        onPressed: busy
                            ? null
                            : () => widget.controller.acquire(
                                CaptureImageSource.camera,
                              ),
                        icon: const Icon(Icons.camera_alt_outlined),
                        label: const Text(AppStrings.captureCamera),
                      ),
                      OutlinedButton.icon(
                        key: const Key('capture_gallery'),
                        onPressed: busy
                            ? null
                            : () => widget.controller.acquire(
                                CaptureImageSource.gallery,
                              ),
                        icon: const Icon(Icons.photo_library_outlined),
                        label: const Text(AppStrings.captureGallery),
                      ),
                    ],
                  ),
                  if (progress != null) ...[
                    const SizedBox(height: AppSpacing.md),
                    Row(
                      children: [
                        const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Text(progress),
                      ],
                    ),
                  ],
                  if (failure != null) ...[
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      failure,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ],
                  if (ready.image case final image?) ...[
                    const SizedBox(height: AppSpacing.lg),
                    ClipRRect(
                      key: const Key('capture_preview'),
                      borderRadius: BorderRadius.circular(12),
                      child: Image.file(
                        File(image.path),
                        height: 180,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            const SizedBox(
                              height: 90,
                              child: Center(
                                child: Icon(Icons.image_outlined, size: 40),
                              ),
                            ),
                      ),
                    ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton.icon(
                        onPressed: busy ? null : widget.controller.removeImage,
                        icon: const Icon(Icons.close),
                        label: const Text('移除图片'),
                      ),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.lg),
                  Text('内容类型', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: AppSpacing.sm),
                  SegmentedButton<CaptureContentType>(
                    segments: const [
                      ButtonSegment(
                        value: CaptureContentType.word,
                        label: Text('单词'),
                      ),
                      ButtonSegment(
                        value: CaptureContentType.sentence,
                        label: Text('句子'),
                      ),
                    ],
                    selected: {ready.contentType},
                    onSelectionChanged: busy
                        ? null
                        : (values) => widget.controller.selectContentType(
                            values.single,
                          ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Text('记忆风格', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: AppSpacing.sm),
                  Wrap(
                    spacing: AppSpacing.sm,
                    children: [
                      ChoiceChip(
                        label: const Text('自动'),
                        selected: ready.memoryStyle == CaptureMemoryStyle.auto,
                        onSelected: busy
                            ? null
                            : (_) => widget.controller.selectMemoryStyle(
                                CaptureMemoryStyle.auto,
                              ),
                      ),
                      ChoiceChip(
                        label: const Text('谐音故事'),
                        selected:
                            ready.memoryStyle ==
                            CaptureMemoryStyle.phoneticStory,
                        onSelected: busy
                            ? null
                            : (_) => widget.controller.selectMemoryStyle(
                                CaptureMemoryStyle.phoneticStory,
                              ),
                      ),
                      ChoiceChip(
                        label: const Text('语义场景'),
                        selected:
                            ready.memoryStyle ==
                            CaptureMemoryStyle.semanticScene,
                        onSelected: busy
                            ? null
                            : (_) => widget.controller.selectMemoryStyle(
                                CaptureMemoryStyle.semanticScene,
                              ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  FilledButton.icon(
                    key: const Key('capture_continue'),
                    onPressed: busy ? null : _continue,
                    icon: const Icon(Icons.auto_awesome),
                    label: const Text(AppStrings.captureContinue),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    ),
  );

  CaptureReady _readyFrom(CaptureState state) => switch (state) {
    CaptureReady ready => ready,
    CaptureSelectingImage(:final previous) => previous,
    CaptureCroppingImage(:final previous) => previous,
    CaptureRecognizingText(:final previous) => previous,
    CaptureSaving(:final previous) => previous,
    CaptureFailure(:final previous) => previous,
  };

  void _syncText(String value) {
    if (_textController.text == value) return;
    _textController.value = TextEditingValue(
      text: value,
      selection: TextSelection.collapsed(offset: value.length),
    );
  }

  Future<void> _continue() async {
    try {
      await widget.controller.submit();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('已保存，正在同步')));
    } on FormatException {
      if (!mounted) return;
      setState(() => _validationMessage = '请输入要记忆的英文内容');
    } catch (_) {
      // The controller exposes a stable local persistence message.
    }
  }
}
