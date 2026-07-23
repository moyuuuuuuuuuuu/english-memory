import 'package:flutter/foundation.dart';

import '../data/capture_service_exception.dart';
import '../data/image_crop_service.dart';
import '../data/image_source_service.dart';
import '../data/text_recognition_service.dart';
import '../domain/capture_content_type.dart';
import '../domain/capture_draft.dart';
import '../domain/capture_memory_style.dart';
import '../domain/recognized_text_normalizer.dart';
import 'capture_state.dart';

final class CaptureController extends ChangeNotifier {
  CaptureController(
    this._source,
    this._cropper,
    this._recognizer, {
    Future<String> Function(CaptureDraft draft)? saveDraft,
  }) : _saveDraft = saveDraft ?? _unconfiguredSave;

  final ImageSourceService _source;
  final ImageCropService _cropper;
  final TextRecognitionService _recognizer;
  final Future<String> Function(CaptureDraft draft) _saveDraft;
  Future<String>? _submitInFlight;

  CaptureState _state = const CaptureReady(
    text: '',
    contentType: CaptureContentType.word,
    memoryStyle: CaptureMemoryStyle.auto,
  );

  CaptureState get state => _state;

  Future<void> acquire(CaptureImageSource source) async {
    if (_state is CaptureSelectingImage ||
        _state is CaptureCroppingImage ||
        _state is CaptureRecognizingText ||
        _state is CaptureSaving) {
      return;
    }

    final previous = _ready;
    _setState(CaptureSelectingImage(previous));
    try {
      final selected = await _source.pick(source);
      if (selected == null) {
        _setState(previous);
        return;
      }

      _setState(CaptureCroppingImage(previous));
      final cropped = await _cropper.crop(selected);
      if (cropped == null) {
        _setState(previous);
        return;
      }

      _setState(CaptureRecognizingText(previous, cropped));
      final recognized = normalizeRecognizedText(
        await _recognizer.recognize(cropped),
      );
      final previousWithImage = previous.copyWith(image: cropped);
      if (!RegExp('[A-Za-z]').hasMatch(recognized)) {
        _setState(CaptureFailure(previousWithImage, '没有识别到英文，请手动编辑或选择其他图片。'));
        return;
      }

      _setState(
        previousWithImage.copyWith(
          text: recognized,
          contentType: suggestContentType(recognized),
        ),
      );
    } on CaptureServiceException catch (error) {
      _setState(CaptureFailure(previous, _messageFor(error.failure)));
    }
  }

  void editText(String text) {
    _setState(_ready.copyWith(text: text));
  }

  void selectContentType(CaptureContentType value) {
    _setState(_ready.copyWith(contentType: value));
  }

  void selectMemoryStyle(CaptureMemoryStyle value) {
    _setState(_ready.copyWith(memoryStyle: value));
  }

  void removeImage() {
    _setState(_ready.copyWith(removeImage: true));
  }

  CaptureDraft createDraft() {
    final ready = _ready;
    return CaptureDraft(
      text: ready.text,
      contentType: ready.contentType,
      memoryStyle: ready.memoryStyle,
    );
  }

  Future<String> submit() {
    final current = _submitInFlight;
    if (current != null) return current;

    late final Future<String> running;
    running = _performSubmit().whenComplete(() {
      if (identical(_submitInFlight, running)) {
        _submitInFlight = null;
      }
    });
    _submitInFlight = running;
    return running;
  }

  Future<String> _performSubmit() async {
    final previous = _ready;
    final draft = CaptureDraft(
      text: previous.text,
      contentType: previous.contentType,
      memoryStyle: previous.memoryStyle,
    );
    _setState(CaptureSaving(previous));
    try {
      final localId = await _saveDraft(draft);
      _setState(
        const CaptureReady(
          text: '',
          contentType: CaptureContentType.word,
          memoryStyle: CaptureMemoryStyle.auto,
        ),
      );
      return localId;
    } catch (_) {
      _setState(CaptureFailure(previous, '无法保存到本机，请重试。'));
      rethrow;
    }
  }

  CaptureReady get _ready => switch (_state) {
    CaptureReady state => state,
    CaptureSelectingImage(:final previous) => previous,
    CaptureCroppingImage(:final previous) => previous,
    CaptureRecognizingText(:final previous) => previous,
    CaptureSaving(:final previous) => previous,
    CaptureFailure(:final previous) => previous,
  };

  void _setState(CaptureState value) {
    _state = value;
    notifyListeners();
  }

  String _messageFor(CaptureServiceFailure failure) => switch (failure) {
    CaptureServiceFailure.permissionDenied => '请在系统设置中允许访问相机或照片。',
    CaptureServiceFailure.cameraUnavailable => '相机暂不可用，请选择照片或手动输入。',
    CaptureServiceFailure.selectionFailed => '无法读取图片，请重试。',
    CaptureServiceFailure.cropFailed => '图片裁剪失败，请重试。',
    CaptureServiceFailure.recognitionFailed => '本地英文识别失败，请重试或手动输入。',
  };

  static Future<String> _unconfiguredSave(CaptureDraft _) =>
      Future.error(StateError('Capture persistence is not configured.'));

  @override
  void dispose() {
    _recognizer.dispose();
    super.dispose();
  }
}
