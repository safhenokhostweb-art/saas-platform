class ApiResponse<T> {
  const ApiResponse({required this.data, this.meta = const {}, this.message});

  final T data;
  final Map<String, dynamic> meta;
  final String? message;

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic) fromData,
  ) =>
      ApiResponse(
        data: fromData(json['data']),
        meta: (json['meta'] as Map<String, dynamic>?) ?? {},
        message: json['message'] as String?,
      );
}
