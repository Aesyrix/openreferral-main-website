// OpenReferral API Client Stub for Flutter
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'models.dart';

class OpenReferralApiClient {
  final String baseUrl;
  String? _authToken;

  OpenReferralApiClient({this.baseUrl = 'https://openreferral.us/api/v1'});

  void setAuthToken(String token) {
    _authToken = token;
  }

  Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (_authToken != null) 'Authorization': 'Bearer $_authToken',
  };

  Future<User> login(String email, String password) async {
    final res = await http.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'email': email, 'password': password, 'device_name': 'flutter-app'}),
    );

    final data = jsonDecode(res.body);
    if (res.statusCode == 200 && data['success'] == true) {
      setAuthToken(data['data']['token']);
      return User.fromJson(data['data']['user']);
    } else {
      throw Exception(data['message'] ?? 'Login failed');
    }
  }

  Future<List<Lead>> fetchLeads({String? status}) async {
    final url = Uri.parse('$baseUrl/leads').replace(queryParameters: {
      if (status != null) 'status': status,
    });

    final res = await http.get(url, headers: _headers);
    final data = jsonDecode(res.body);

    if (res.statusCode == 200 && data['success'] == true) {
      final List list = data['data'];
      return list.map((item) => Lead.fromJson(item)).toList();
    } else {
      throw Exception('Failed to fetch leads');
    }
  }

  Future<Lead> acceptLead(String uuid) async {
    final res = await http.post(
      Uri.parse('$baseUrl/leads/$uuid/accept'),
      headers: _headers,
    );

    final data = jsonDecode(res.body);
    if (res.statusCode == 200 && data['success'] == true) {
      return Lead.fromJson(data['data']);
    } else {
      throw Exception(data['message'] ?? 'Failed to accept lead');
    }
  }

  Future<Lead> rejectLead(String uuid, String reason, {String? note}) async {
    final res = await http.post(
      Uri.parse('$baseUrl/leads/$uuid/reject'),
      headers: _headers,
      body: jsonEncode({'reason': reason, if (note != null) 'note': note}),
    );

    final data = jsonDecode(res.body);
    if (res.statusCode == 200 && data['success'] == true) {
      return Lead.fromJson(data['data']);
    } else {
      throw Exception(data['message'] ?? 'Failed to reject lead');
    }
  }
}
