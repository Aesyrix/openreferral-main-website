// Dart Models for OpenReferral Mobile App
// Auto-generated immutable models for Flutter SDK integration

class User {
  final String uuid;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final String avatarUrl;

  User({
    required this.uuid,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    required this.avatarUrl,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      uuid: json['uuid'],
      name: json['name'],
      email: json['email'],
      phone: json['phone'],
      role: json['role'],
      avatarUrl: json['avatar_url'] ?? '',
    );
  }
}

class Lead {
  final String uuid;
  final String publicRef;
  final String type;
  final String status;
  final String firstName;
  final String lastName;
  final String phone;
  final String? email;
  final String city;
  final String state;
  final String postalCode;
  final String? acceptByAt;

  Lead({
    required this.uuid,
    required this.publicRef,
    required this.type,
    required this.status,
    required this.firstName,
    required this.lastName,
    required this.phone,
    this.email,
    required this.city,
    required this.state,
    required this.postalCode,
    this.acceptByAt,
  });

  factory Lead.fromJson(Map<String, dynamic> json) {
    return Lead(
      uuid: json['uuid'],
      publicRef: json['public_ref'],
      type: json['type'] is Map ? json['type']['value'] : json['type'],
      status: json['status'] is Map ? json['status']['value'] : json['status'],
      firstName: json['first_name'],
      lastName: json['last_name'],
      phone: json['phone'],
      email: json['email'],
      city: json['city'],
      state: json['state'] ?? 'FL',
      postalCode: json['postal_code'],
      acceptByAt: json['accept_by_at'],
    );
  }
}
