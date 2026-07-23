enum CaptureServiceFailure {
  permissionDenied,
  cameraUnavailable,
  selectionFailed,
  cropFailed,
  recognitionFailed,
}

final class CaptureServiceException implements Exception {
  const CaptureServiceException(this.failure);

  final CaptureServiceFailure failure;

  @override
  String toString() => 'CaptureServiceException(${failure.name})';
}
